import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../state/auth.dart';
import '../../theme.dart';
import '../../widgets/logo.dart';

/// Three intro screens shown once on a new phone, before the homepage.
class OnboardingScreen extends StatefulWidget {
  const OnboardingScreen({super.key});

  @override
  State<OnboardingScreen> createState() => _OnboardingScreenState();
}

class _Page {
  const _Page({required this.icon, required this.color, required this.chip, required this.title, required this.body});
  final IconData icon;
  final Color color;
  final String chip;
  final String title;
  final String body;
}

const _pages = [
  _Page(
    icon: Icons.mic_rounded,
    color: AppColors.brand,
    chip: 'Band 6.5',
    title: 'Speak your answers out loud',
    body: 'Practise IELTS Speaking the way the real test works. An AI examiner listens and gives you an estimated band in minutes.',
  ),
  _Page(
    icon: Icons.edit_note_rounded,
    color: AppColors.accent,
    chip: '3 fixes',
    title: 'See exactly what to fix',
    body: 'Writing, Reading and Listening practice with sentence-level corrections and model answers, so you know what costs you marks.',
  ),
  _Page(
    icon: Icons.trending_up_rounded,
    color: Color(0xFF6366F1),
    chip: 'Target 7.0',
    title: 'Reach your band before test day',
    body: 'Mock exams, a study plan and progress tracking keep you on course for the score you need to study, work or settle abroad.',
  ),
];

class _OnboardingScreenState extends State<OnboardingScreen> {
  final _controller = PageController();
  int _index = 0;

  bool get _last => _index == _pages.length - 1;

  void _finish() => context.read<AuthState>().finishOnboarding();

  void _next() {
    if (_last) return _finish();
    _controller.nextPage(duration: const Duration(milliseconds: 320), curve: Curves.easeOutCubic);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 8, 8),
            child: Row(children: [
              const Logo(),
              const Spacer(),
              AnimatedOpacity(
                opacity: _last ? 0 : 1,
                duration: const Duration(milliseconds: 200),
                child: TextButton(onPressed: _last ? null : _finish, child: const Text('Skip')),
              ),
            ]),
          ),
          Expanded(
            child: PageView.builder(
              controller: _controller,
              itemCount: _pages.length,
              onPageChanged: (i) => setState(() => _index = i),
              itemBuilder: (_, i) => _PageView(page: _pages[i]),
            ),
          ),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              for (var i = 0; i < _pages.length; i++)
                AnimatedContainer(
                  duration: const Duration(milliseconds: 250),
                  margin: const EdgeInsets.symmetric(horizontal: 4),
                  width: i == _index ? 24 : 8,
                  height: 8,
                  decoration: BoxDecoration(
                    color: i == _index ? AppColors.brand : AppColors.border,
                    borderRadius: BorderRadius.circular(4),
                  ),
                ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 24, 20, 16),
            child: SizedBox(
              width: double.infinity,
              child: FilledButton(onPressed: _next, child: Text(_last ? 'Get started' : 'Next')),
            ),
          ),
        ]),
      ),
    );
  }
}

class _PageView extends StatelessWidget {
  const _PageView({required this.page});
  final _Page page;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(24, 12, 24, 24),
      child: Column(children: [
        _Illustration(page: page),
        const SizedBox(height: 36),
        Text(page.title, textAlign: TextAlign.center, style: grotesk(size: 28)),
        const SizedBox(height: 12),
        Text(
          page.body,
          textAlign: TextAlign.center,
          style: const TextStyle(fontSize: 16, color: AppColors.muted, height: 1.5),
        ),
      ]),
    );
  }
}

/// A soft tinted circle with the page's icon and a small result chip, drawn in code (no image assets).
class _Illustration extends StatelessWidget {
  const _Illustration({required this.page});
  final _Page page;

  @override
  Widget build(BuildContext context) {
    final size = (MediaQuery.sizeOf(context).width * 0.62).clamp(180.0, 280.0);
    return SizedBox(
      width: size,
      height: size,
      child: Stack(clipBehavior: Clip.none, alignment: Alignment.center, children: [
        Container(
          decoration: BoxDecoration(shape: BoxShape.circle, color: page.color.withValues(alpha: 0.10)),
        ),
        Container(
          width: size * 0.62,
          height: size * 0.62,
          decoration: BoxDecoration(shape: BoxShape.circle, color: page.color.withValues(alpha: 0.18)),
        ),
        Container(
          width: size * 0.38,
          height: size * 0.38,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: page.color,
            boxShadow: [BoxShadow(color: page.color.withValues(alpha: 0.35), blurRadius: 24, offset: const Offset(0, 10))],
          ),
          child: Icon(page.icon, color: Colors.white, size: size * 0.18),
        ),
        Positioned(
          bottom: size * 0.08,
          right: -8,
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: AppColors.border),
              boxShadow: const [BoxShadow(color: Color(0x14000000), blurRadius: 16, offset: Offset(0, 6))],
            ),
            child: Row(mainAxisSize: MainAxisSize.min, children: [
              Icon(Icons.check_circle_rounded, color: page.color, size: 18),
              const SizedBox(width: 6),
              Text(page.chip, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
            ]),
          ),
        ),
      ]),
    );
  }
}
