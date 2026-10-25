import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_native_splash/flutter_native_splash.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'api/api_client.dart';
import 'router.dart';
import 'state/auth.dart';
import 'state/billing.dart';
import 'state/push.dart';
import 'theme.dart';
import 'widgets/limit_dialog.dart';

void main() {
  final binding = WidgetsFlutterBinding.ensureInitialized();
  // Keep the native splash up until we know whether someone is signed in (no flash of a blank screen).
  FlutterNativeSplash.preserve(widgetsBinding: binding);
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  final api = ApiClient();
  final auth = AuthState(api)..boot();
  final billing = BillingState(api, auth);
  final push = PushService(api, auth);
  runApp(JapaCoachApp(api: api, auth: auth, billing: billing, push: push));
}

class JapaCoachApp extends StatefulWidget {
  const JapaCoachApp({super.key, required this.api, required this.auth, required this.billing, required this.push});

  final ApiClient api;
  final AuthState auth;
  final BillingState billing;
  final PushService push;

  @override
  State<JapaCoachApp> createState() => _JapaCoachAppState();
}

class _JapaCoachAppState extends State<JapaCoachApp> {
  late final GoRouter _router = buildRouter(widget.auth);
  final _messenger = GlobalKey<ScaffoldMessengerState>();
  int _seenBillingMessage = 0;

  @override
  void initState() {
    super.initState();
    widget.auth.addListener(_removeSplashWhenReady);
    _removeSplashWhenReady();

    widget.push.onOpen = (route) => _router.push(route);
    widget.push.onForeground = (title, body, route) {
      _messenger.currentState
        ?..hideCurrentSnackBar()
        ..showSnackBar(SnackBar(
          content: Text('$title\n$body'),
          behavior: SnackBarBehavior.floating,
          duration: const Duration(seconds: 6),
          action: route == null ? null : SnackBarAction(label: 'Open', onPressed: () => _router.push(route)),
        ));
    };
    widget.push.init();

    widget.api.onLimitReached = (error) {
      final context = _router.routerDelegate.navigatorKey.currentContext;
      if (context != null) showLimitDialog(context, error, canBuy: widget.billing.supported);
    };
    // Payment results can arrive on any screen (e.g. a purchase finished after restarting the app).
    widget.billing.addListener(() {
      final b = widget.billing;
      if (b.messageId != _seenBillingMessage && b.message != null) {
        _seenBillingMessage = b.messageId;
        _messenger.currentState
          ?..hideCurrentSnackBar()
          ..showSnackBar(SnackBar(content: Text(b.message!), behavior: SnackBarBehavior.floating));
      }
    });
  }

  void _removeSplashWhenReady() {
    if (widget.auth.status == AuthStatus.loading) return;
    widget.auth.removeListener(_removeSplashWhenReady);
    FlutterNativeSplash.remove();
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider.value(value: widget.api),
        ChangeNotifierProvider.value(value: widget.auth),
        ChangeNotifierProvider.value(value: widget.billing),
      ],
      child: MaterialApp.router(
        title: 'Japa Coach',
        debugShowCheckedModeBanner: false,
        theme: buildTheme(),
        scaffoldMessengerKey: _messenger,
        routerConfig: _router,
      ),
    );
  }
}
