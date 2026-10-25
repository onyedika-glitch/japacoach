import 'dart:async';
import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../api/api_client.dart';
import '../config.dart';
import 'auth.dart';

/// Push notifications through Firebase Cloud Messaging. Does nothing until the Firebase settings
/// are passed at build time (see AppConfig and README.md).
class PushService {
  PushService(this.api, this.auth) {
    auth.addListener(_onAuthChanged);
    // Unregister this phone while the login is still valid, so pushes stop after logout.
    auth.beforeLogout = unregister;
  }

  final ApiClient api;
  final AuthState auth;
  bool _ready = false;
  bool _registered = false;
  StreamSubscription<String>? _tokenRefresh;

  /// Called with the in-app route (e.g. /speaking/report/12) of a tapped notification.
  void Function(String route)? onOpen;

  /// Called for notifications that arrive while the app is open (Android shows nothing by itself then).
  void Function(String title, String body, String? route)? onForeground;

  Future<void> init() async {
    if (!AppConfig.pushEnabled || _ready || kIsWeb) return;
    try {
      await Firebase.initializeApp(
        options: const FirebaseOptions(
          apiKey: AppConfig.firebaseApiKey,
          appId: AppConfig.firebaseAppId,
          projectId: AppConfig.firebaseProjectId,
          messagingSenderId: AppConfig.firebaseSenderId,
        ),
      );
      _ready = true;
      FirebaseMessaging.onMessageOpenedApp.listen(_open);
      FirebaseMessaging.onMessage.listen((m) {
        final n = m.notification;
        if (n != null) onForeground?.call(n.title ?? 'Japa Coach', n.body ?? '', m.data['route'] as String?);
      });
      final initial = await FirebaseMessaging.instance.getInitialMessage();
      if (initial != null) _open(initial);
      _onAuthChanged();
    } catch (e) {
      debugPrint('Push init failed: $e');
    }
  }

  void _open(RemoteMessage message) {
    final route = message.data['route'] as String?;
    if (route != null && route.startsWith('/')) onOpen?.call(route);
  }

  void _onAuthChanged() {
    if (auth.status == AuthStatus.signedIn && !_registered) register();
    if (auth.status == AuthStatus.signedOut) _registered = false;
  }

  /// Ask permission (Android 13+) and tell the API where to send pushes for this user.
  Future<void> register() async {
    if (!_ready || _registered) return;
    _registered = true;
    try {
      await FirebaseMessaging.instance.requestPermission();
      // The classic registration token: the server sends to it with the FCM v1 API (same as Gigora).
      // ignore: deprecated_member_use
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await _send(token);
      // ignore: deprecated_member_use
      _tokenRefresh ??= FirebaseMessaging.instance.onTokenRefresh.listen((t) => _send(t));
    } catch (e) {
      _registered = false;
      debugPrint('Push registration failed: $e');
    }
  }

  Future<void> unregister() async {
    if (!_ready) return;
    try {
      // ignore: deprecated_member_use
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null) await api.delete('/api/me/devices', data: {'token': token});
    } catch (_) {
      // Logging out must not fail because of this; the server also drops dead tokens.
    }
    _registered = false;
  }

  Future<void> _send(String token) async {
    if (auth.status != AuthStatus.signedIn) return;
    await api.post('/api/me/devices', data: {'token': token, 'platform': Platform.isIOS ? 'ios' : 'android'});
  }
}
