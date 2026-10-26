import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import 'screens/auth/login_screen.dart';
import 'screens/auth/onboarding_screen.dart';
import 'screens/auth/signup_screen.dart';
import 'screens/auth/welcome_screen.dart';
import 'screens/home/dashboard_screen.dart';
import 'screens/mock/mock_exam_screen.dart';
import 'screens/mock/mock_list_screen.dart';
import 'screens/more/class_screen.dart';
import 'screens/more/more_screen.dart';
import 'screens/more/plans_screen.dart';
import 'screens/more/referrals_screen.dart';
import 'screens/more/settings_screen.dart';
import 'screens/more/study_plan_screen.dart';
import 'screens/practice/practice_home_screen.dart';
import 'screens/practice/practice_result_screen.dart';
import 'screens/practice/practice_test_screen.dart';
import 'screens/shell.dart';
import 'screens/speaking/speaking_home_screen.dart';
import 'screens/speaking/speaking_report_screen.dart';
import 'screens/speaking/speaking_test_screen.dart';
import 'screens/writing/writing_home_screen.dart';
import 'screens/writing/writing_report_screen.dart';
import 'screens/writing/writing_task_screen.dart';
import 'state/auth.dart';
import 'theme.dart';

int _id(GoRouterState s, [String name = 'id']) => int.parse(s.pathParameters[name]!);
int? _mock(GoRouterState s) => int.tryParse(s.uri.queryParameters['mock'] ?? '');

GoRouter buildRouter(AuthState auth) {
  return GoRouter(
    initialLocation: '/home',
    refreshListenable: auth,
    redirect: (context, state) {
      final loc = state.matchedLocation;
      final isAuthPage = loc == '/welcome' || loc == '/login' || loc == '/signup';
      if (auth.status == AuthStatus.loading) return loc == '/splash' ? null : '/splash';
      if (auth.status == AuthStatus.signedOut && !auth.onboarded) return loc == '/onboarding' ? null : '/onboarding';
      if (auth.status == AuthStatus.signedOut) return isAuthPage ? null : '/welcome';
      if (isAuthPage || loc == '/splash' || loc == '/onboarding') return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const _Splash()),
      GoRoute(path: '/onboarding', builder: (_, _) => const OnboardingScreen()),
      GoRoute(path: '/welcome', builder: (_, _) => const WelcomeScreen()),
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/signup', builder: (_, _) => const SignupScreen()),
      StatefulShellRoute.indexedStack(
        builder: (context, state, shell) => AppShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/home', builder: (_, _) => const DashboardScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/speaking', builder: (_, _) => const SpeakingHomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/writing', builder: (_, _) => const WritingHomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/practice', builder: (_, s) => PracticeHomeScreen(initialModule: s.uri.queryParameters['module']))]),
          StatefulShellBranch(routes: [GoRoute(path: '/more', builder: (_, _) => const MoreScreen())]),
        ],
      ),
      GoRoute(path: '/speaking/test/:id', builder: (_, s) => SpeakingTestScreen(attemptId: _id(s), mockId: _mock(s))),
      GoRoute(path: '/speaking/report/:id', builder: (_, s) => SpeakingReportScreen(attemptId: _id(s))),
      GoRoute(path: '/writing/task/:id', builder: (_, s) => WritingTaskScreen(taskId: _id(s), mockId: _mock(s))),
      GoRoute(path: '/writing/report/:id', builder: (_, s) => WritingReportScreen(attemptId: _id(s))),
      GoRoute(path: '/practice/test/:id', builder: (_, s) => PracticeTestScreen(attemptId: _id(s), mockId: _mock(s))),
      GoRoute(path: '/practice/result/:id', builder: (_, s) => PracticeResultScreen(attemptId: _id(s))),
      GoRoute(path: '/mock', builder: (_, _) => const MockListScreen()),
      GoRoute(path: '/mock/:id', builder: (_, s) => MockExamScreen(examId: _id(s))),
      GoRoute(path: '/study-plan', builder: (_, _) => const StudyPlanScreen()),
      GoRoute(path: '/referrals', builder: (_, _) => const ReferralsScreen()),
      GoRoute(path: '/settings', builder: (_, _) => const SettingsScreen()),
      GoRoute(path: '/plans', builder: (_, _) => const PlansScreen()),
      GoRoute(path: '/class', builder: (_, _) => const ClassScreen()),
    ],
  );
}

/// Shown while the app starts. Matches the native splash (assets/splash-*.png, see pubspec.yaml)
/// so the hand-over is seamless, and adds the logo where Android 12+ leaves it out.
class _Splash extends StatelessWidget {
  const _Splash();

  @override
  Widget build(BuildContext context) => AnnotatedRegion<SystemUiOverlayStyle>(
        value: SystemUiOverlayStyle.light.copyWith(statusBarColor: Colors.transparent),
        child: Scaffold(
          backgroundColor: AppColors.brand,
          body: Stack(children: [
            Center(child: Image.asset('assets/splash-logo.png', width: 128, height: 128)),
            Positioned(
              left: 0,
              right: 0,
              bottom: 32 + MediaQuery.paddingOf(context).bottom,
              child: Center(child: Image.asset('assets/splash-branding.png', width: 150)),
            ),
          ]),
        ),
      );
}
