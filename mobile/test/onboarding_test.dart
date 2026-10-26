import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';

import 'package:japa_coach/api/api_client.dart';
import 'package:japa_coach/screens/auth/onboarding_screen.dart';
import 'package:japa_coach/state/auth.dart';

Future<AuthState> _pump(WidgetTester tester) async {
  final auth = AuthState(ApiClient());
  await tester.pumpWidget(ChangeNotifierProvider.value(value: auth, child: const MaterialApp(home: OnboardingScreen())));
  await tester.pumpAndSettle();
  return auth;
}

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);

  testWidgets('a new install has not seen the intro', (tester) async {
    expect(AuthState(ApiClient()).onboarded, isFalse);
  });

  testWidgets('Next walks through three screens, then Get started finishes', (tester) async {
    final auth = await _pump(tester);
    expect(find.text('Speak your answers out loud'), findsOneWidget);

    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('See exactly what to fix'), findsOneWidget);

    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();
    expect(find.text('Reach your band before test day'), findsOneWidget);
    expect(auth.onboarded, isFalse);

    await tester.tap(find.text('Get started'));
    await tester.pump();
    expect(auth.onboarded, isTrue);
  });

  testWidgets('Skip finishes from the first screen', (tester) async {
    final auth = await _pump(tester);
    await tester.tap(find.text('Skip'));
    await tester.pump();
    expect(auth.onboarded, isTrue);
  });
}
