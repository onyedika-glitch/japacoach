import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_sign_in/google_sign_in.dart';

import '../api/api_client.dart';
import '../config.dart';
import '../models/models.dart';

enum AuthStatus { loading, signedOut, signedIn }

class AuthState extends ChangeNotifier {
  AuthState(this.api) {
    api.onUnauthorized = () => _clear();
  }

  static const _tokenKey = 'japa_token';
  static const _storage = FlutterSecureStorage();

  final ApiClient api;
  AuthStatus status = AuthStatus.loading;
  User? user;
  bool _googleReady = false;

  bool get googleEnabled => AppConfig.googleServerClientId.isNotEmpty;

  /// Restore a saved session on app start.
  Future<void> boot() async {
    try {
      api.token = await _storage.read(key: _tokenKey);
    } catch (_) {
      api.token = null;
    }
    if (api.token == null) {
      status = AuthStatus.signedOut;
      notifyListeners();
      return;
    }
    try {
      await refreshUser();
      status = AuthStatus.signedIn;
    } on ApiException catch (e) {
      // Offline: keep the session and let screens show their own errors.
      status = e.status == 401 ? AuthStatus.signedOut : AuthStatus.signedIn;
      if (e.status == 401) await _clear(notify: false);
    }
    notifyListeners();
  }

  Future<void> refreshUser() async {
    final data = await api.get('/api/me');
    user = User.fromJson(Map<String, dynamic>.from(data['user'] as Map));
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final data = await api.post('/api/mobile/login', data: {'email': email.trim(), 'password': password, 'device_name': _device});
    await _signedIn(data);
  }

  Future<void> register({required String name, required String email, required String password, String? referralCode}) async {
    final data = await api.post('/api/mobile/register', data: {
      'name': name.trim(),
      'email': email.trim(),
      'password': password,
      'password_confirmation': password,
      if (referralCode != null && referralCode.trim().isNotEmpty) 'referral_code': referralCode.trim(),
      'device_name': _device,
    });
    await _signedIn(data);
  }

  /// Returns false if the user closed the Google sheet.
  Future<bool> signInWithGoogle({String? referralCode}) async {
    final google = GoogleSignIn.instance;
    if (!_googleReady) {
      await google.initialize(serverClientId: AppConfig.googleServerClientId);
      _googleReady = true;
    }
    final GoogleSignInAccount account;
    try {
      account = await google.authenticate();
    } on GoogleSignInException catch (e) {
      // Android reports a misconfigured app (e.g. an unregistered signing key) as "canceled" too,
      // so always explain instead of failing silently.
      throw ApiException(e.code == GoogleSignInExceptionCode.canceled
          ? "Google sign-in didn't complete. Try again, or log in with your email and password."
          : 'Google sign-in failed. Please try again or use your email.');
    }
    final idToken = account.authentication.idToken;
    if (idToken == null) throw ApiException('Google sign-in failed. Please try again or use your email.');
    final data = await api.post('/api/mobile/google', data: {
      'id_token': idToken,
      if (referralCode != null && referralCode.trim().isNotEmpty) 'referral_code': referralCode.trim(),
      'device_name': _device,
    });
    await _signedIn(data);
    return true;
  }

  Future<void> updateProfile(Map<String, dynamic> changes) async {
    final data = await api.patch('/api/me', data: changes);
    user = User.fromJson(Map<String, dynamic>.from(data['user'] as Map));
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await api.post('/api/mobile/logout');
    } catch (_) {
      // Signing out locally is what matters.
    }
    await _clear();
  }

  Future<void> deleteAccount() async {
    await api.delete('/api/me', data: {'confirm': 'DELETE'});
    await _clear();
  }

  Future<void> _signedIn(Map<String, dynamic> data) async {
    api.token = data['token'] as String;
    user = User.fromJson(Map<String, dynamic>.from(data['user'] as Map));
    await _storage.write(key: _tokenKey, value: api.token);
    status = AuthStatus.signedIn;
    notifyListeners();
  }

  Future<void> _clear({bool notify = true}) async {
    api.token = null;
    user = null;
    status = AuthStatus.signedOut;
    try {
      await _storage.delete(key: _tokenKey);
      if (_googleReady) await GoogleSignIn.instance.signOut();
    } catch (_) {}
    if (notify) notifyListeners();
  }

  String get _device => switch (defaultTargetPlatform) {
        TargetPlatform.android => 'Android app',
        TargetPlatform.iOS => 'iOS app',
        _ => 'Mobile app',
      };
}
