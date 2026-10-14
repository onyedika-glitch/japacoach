import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'api/api_client.dart';
import 'router.dart';
import 'state/auth.dart';
import 'state/billing.dart';
import 'theme.dart';
import 'widgets/limit_dialog.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  final api = ApiClient();
  final auth = AuthState(api)..boot();
  final billing = BillingState(api, auth);
  runApp(JapaCoachApp(api: api, auth: auth, billing: billing));
}

class JapaCoachApp extends StatefulWidget {
  const JapaCoachApp({super.key, required this.api, required this.auth, required this.billing});

  final ApiClient api;
  final AuthState auth;
  final BillingState billing;

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
