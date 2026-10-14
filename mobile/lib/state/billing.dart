import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:in_app_purchase/in_app_purchase.dart';

import '../api/api_client.dart';
import 'auth.dart';

/// Google Play purchases of plan passes. Every purchase is verified by our server, which grants the
/// plan and consumes it; only then do we mark it complete here. Unfinished purchases are retried on start.
class BillingState extends ChangeNotifier {
  BillingState(this.api, this.auth) {
    auth.addListener(_onAuthChanged);
    _onAuthChanged();
  }

  static const productIds = {'pass_weekly', 'pass_monthly', 'pass_sprint'};

  /// Plan id on our server for each Play product.
  static const planForProduct = {'pass_weekly': 'weekly', 'pass_monthly': 'monthly', 'pass_sprint': 'sprint'};

  final ApiClient api;
  final AuthState auth;
  final _iap = InAppPurchase.instance;
  StreamSubscription<List<PurchaseDetails>>? _sub;
  String? _accountId;

  bool get supported => !kIsWeb && Platform.isAndroid;
  bool available = false;
  bool loading = false;
  String? busyProductId;
  Map<String, ProductDetails> products = {};

  /// Latest message for the user (shown as a snackbar), with a counter so the same text can repeat.
  String? message;
  int messageId = 0;

  void _onAuthChanged() {
    if (auth.status == AuthStatus.signedIn && supported && _sub == null) {
      _start();
    } else if (auth.status == AuthStatus.signedOut) {
      _sub?.cancel();
      _sub = null;
      _accountId = null;
    }
  }

  Future<void> _start() async {
    _sub = _iap.purchaseStream.listen(_onPurchases, onError: (_) => _say('Google Play had a problem. Please try again.'));
    await loadProducts();
    if (available) {
      // Re-sends purchases that were paid but not finished (e.g. the app closed mid-way).
      await _iap.restorePurchases();
    }
  }

  Future<void> loadProducts() async {
    if (!supported) return;
    loading = true;
    notifyListeners();
    try {
      available = await _iap.isAvailable();
      if (available) {
        final response = await _iap.queryProductDetails(productIds);
        products = {for (final p in response.productDetails) p.id: p};
      }
    } catch (_) {
      available = false;
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  Future<void> buy(String productId) async {
    final product = products[productId];
    if (product == null || busyProductId != null) return;
    busyProductId = productId;
    notifyListeners();
    try {
      _accountId ??= (await api.get('/api/billing/google-play/account'))['account_id'] as String;
      final started = await _iap.buyConsumable(
        purchaseParam: PurchaseParam(productDetails: product, applicationUserName: _accountId),
        autoConsume: false, // our server consumes after granting the plan
      );
      if (!started) _done();
    } on ApiException catch (e) {
      _done(e.message);
    } catch (_) {
      _done('Could not start the payment. Please try again.');
    }
  }

  Future<void> _onPurchases(List<PurchaseDetails> purchases) async {
    for (final p in purchases) {
      switch (p.status) {
        case PurchaseStatus.pending:
          _say('Payment pending. Your plan starts as soon as Google Play confirms it.');
        case PurchaseStatus.canceled:
          _done();
        case PurchaseStatus.error:
          _done(p.error?.message.isNotEmpty == true ? 'Payment failed: ${p.error!.message}' : 'Payment failed. You were not charged.');
        case PurchaseStatus.purchased || PurchaseStatus.restored:
          await _verify(p);
      }
    }
  }

  Future<void> _verify(PurchaseDetails p) async {
    try {
      final data = await api.post('/api/billing/google-play/verify', data: {
        'product_id': p.productID,
        'purchase_token': p.verificationData.serverVerificationData,
      });
      if (data['status'] == 'pending') {
        _done(data['message'] as String?);
        return;
      }
      if (p.pendingCompletePurchase) await _iap.completePurchase(p);
      await auth.refreshUser();
      _done(data['message'] as String? ?? 'Your plan is active.');
    } on ApiException catch (e) {
      // 409/422: not usable for this account; finish it so Play stops re-sending. Others: retry on next start.
      if ((e.status == 409 || e.status == 422) && p.pendingCompletePurchase) await _iap.completePurchase(p);
      _done(e.status != null && e.status! >= 500
          ? 'We could not confirm your payment yet. It will be retried automatically, and you will not be charged twice.'
          : e.message);
    }
  }

  void _done([String? text]) {
    busyProductId = null;
    if (text != null) {
      _say(text);
    } else {
      notifyListeners();
    }
  }

  void _say(String text) {
    message = text;
    messageId++;
    notifyListeners();
  }

  @override
  void dispose() {
    auth.removeListener(_onAuthChanged);
    _sub?.cancel();
    super.dispose();
  }
}
