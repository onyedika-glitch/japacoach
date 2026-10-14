import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../theme.dart';
import '../../widgets/logo.dart';

class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.brand50,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
          child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Align(alignment: Alignment.centerLeft, child: Logo()),
            const Spacer(),
            Text.rich(
              TextSpan(children: [
                const TextSpan(text: 'Hit your '),
                TextSpan(text: 'IELTS band', style: grotesk(size: 38, color: AppColors.brand)),
                const TextSpan(text: ' before your test date.'),
              ]),
              style: grotesk(size: 38),
            ),
            const SizedBox(height: 16),
            const Text(
              'Practise Speaking, Writing, Reading and Listening with an AI examiner. Get an estimated band and specific fixes in minutes.',
              style: TextStyle(fontSize: 17, color: AppColors.muted, height: 1.45),
            ),
            const SizedBox(height: 28),
            const _Feature(icon: Icons.mic_rounded, text: 'Speak your answers out loud, like the real test'),
            const _Feature(icon: Icons.edit_note_rounded, text: 'Essays marked with corrections and a model answer'),
            const _Feature(icon: Icons.menu_book_rounded, text: 'Full 40-question Reading and Listening tests'),
            const Spacer(),
            FilledButton(onPressed: () => context.push('/signup'), child: const Text('Start free')),
            const SizedBox(height: 12),
            OutlinedButton(onPressed: () => context.push('/login'), child: const Text('I already have an account')),
          ]),
        ),
      ),
    );
  }
}

class _Feature extends StatelessWidget {
  const _Feature({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Row(children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(12)),
            child: Icon(icon, color: AppColors.brand),
          ),
          const SizedBox(width: 12),
          Expanded(child: Text(text, style: const TextStyle(fontWeight: FontWeight.w600))),
        ]),
      );
}
