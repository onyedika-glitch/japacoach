import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import 'api/api_client.dart';
import 'router.dart';
import 'state/auth.dart';
import 'theme.dart';
import 'widgets/limit_dialog.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setPreferredOrientations([DeviceOrientation.portraitUp]);
  final api = ApiClient();
  final auth = AuthState(api)..boot();
  runApp(JapaCoachApp(api: api, auth: auth));
}

class JapaCoachApp extends StatefulWidget {
  const JapaCoachApp({super.key, required this.api, required this.auth});

  final ApiClient api;
  final AuthState auth;

  @override
  State<JapaCoachApp> createState() => _JapaCoachAppState();
}

class _JapaCoachAppState extends State<JapaCoachApp> {
  late final GoRouter _router = buildRouter(widget.auth);

  @override
  void initState() {
    super.initState();
    widget.api.onLimitReached = (error) {
      final context = _router.routerDelegate.navigatorKey.currentContext;
      if (context != null) showLimitDialog(context, error);
    };
  }

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        Provider.value(value: widget.api),
        ChangeNotifierProvider.value(value: widget.auth),
      ],
      child: MaterialApp.router(
        title: 'Japa Coach',
        debugShowCheckedModeBanner: false,
        theme: buildTheme(),
        routerConfig: _router,
      ),
    );
  }
}
