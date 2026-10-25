import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/logo.dart';

/// The homepage people see before signing in: what Japa Coach does, proof, and a way in.
class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        bottom: false,
        child: Column(children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 8, 8, 8),
            child: Row(children: [
              const Logo(),
              const Spacer(),
              TextButton(onPressed: () => context.push('/login'), child: const Text('Log in')),
            ]),
          ),
          const Expanded(child: _Home()),
          _BottomBar(),
        ]),
      ),
    );
  }
}

class _Home extends StatelessWidget {
  const _Home();

  @override
  Widget build(BuildContext context) {
    return ListView(padding: EdgeInsets.zero, children: const [
      _Hero(),
      _HowItWorks(),
      _Modules(),
      _Reviews(),
      _Faq(),
      _FinalCta(),
    ]);
  }
}

class _Section extends StatelessWidget {
  const _Section({required this.eyebrow, required this.title, required this.child, this.color});
  final String eyebrow;
  final String title;
  final Widget child;
  final Color? color;

  @override
  Widget build(BuildContext context) => Container(
        color: color,
        padding: const EdgeInsets.fromLTRB(20, 36, 20, 36),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(eyebrow.toUpperCase(), style: const TextStyle(color: AppColors.brandDark, fontWeight: FontWeight.w700, fontSize: 12, letterSpacing: 1.2)),
          const SizedBox(height: 6),
          Text(title, style: grotesk(size: 26)),
          const SizedBox(height: 18),
          child,
        ]),
      );
}

class _Hero extends StatelessWidget {
  const _Hero();

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(12, 4, 12, 0),
      padding: const EdgeInsets.fromLTRB(20, 28, 20, 24),
      decoration: BoxDecoration(color: AppColors.brand50, borderRadius: BorderRadius.circular(28)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text.rich(
          TextSpan(children: [
            const TextSpan(text: 'Hit your '),
            TextSpan(text: 'IELTS band', style: grotesk(size: 34, color: AppColors.brand)),
            const TextSpan(text: ' before your test date.'),
          ]),
          style: grotesk(size: 34),
        ),
        const SizedBox(height: 12),
        const Text(
          'Practise Speaking, Writing, Reading and Listening with an AI examiner. Get an estimated band and specific fixes in minutes. Built for Nigerians preparing to relocate.',
          style: TextStyle(fontSize: 16, color: AppColors.muted, height: 1.45),
        ),
        const SizedBox(height: 20),
        FilledButton.icon(
          onPressed: () => context.push('/signup'),
          icon: const Icon(Icons.mic_rounded),
          label: const Text('Take a free speaking test'),
        ),
        const SizedBox(height: 8),
        const Center(child: Text('No card needed · Works on any phone', style: TextStyle(color: AppColors.muted, fontSize: 13))),
        const SizedBox(height: 22),
        const _SampleReport(),
      ]),
    );
  }
}

/// A static example of what a marked speaking test looks like.
class _SampleReport extends StatelessWidget {
  const _SampleReport();

  @override
  Widget build(BuildContext context) {
    const criteria = [('Fluency', '6.5'), ('Vocabulary', '6.0'), ('Grammar', '6.5'), ('Pronunciation', '7.0')];
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        boxShadow: [BoxShadow(color: AppColors.ink.withValues(alpha: 0.06), blurRadius: 24, offset: const Offset(0, 8))],
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          const Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Speaking mock · sample report', style: TextStyle(color: AppColors.muted, fontSize: 12)),
              Text('Your estimated band', style: TextStyle(fontWeight: FontWeight.w700)),
            ]),
          ),
          Text('6.5', style: grotesk(size: 40, color: AppColors.brand)),
        ]),
        const SizedBox(height: 12),
        Row(children: [
          for (final (label, value) in criteria)
            Expanded(
              child: Container(
                margin: const EdgeInsets.symmetric(horizontal: 3),
                padding: const EdgeInsets.symmetric(vertical: 8),
                decoration: BoxDecoration(color: AppColors.brand50, borderRadius: BorderRadius.circular(10)),
                child: Column(children: [
                  Text(label, style: const TextStyle(fontSize: 10, color: AppColors.muted)),
                  Text(value, style: grotesk(size: 16)),
                ]),
              ),
            ),
        ]),
        const SizedBox(height: 12),
        Text('"I am living in Lagos since 2019."', style: TextStyle(color: AppColors.bad.withValues(alpha: 0.8), decoration: TextDecoration.lineThrough)),
        const Text('"I have been living in Lagos since 2019."', style: TextStyle(color: AppColors.good, fontWeight: FontWeight.w600)),
        const SizedBox(height: 2),
        const Text('Use the present perfect continuous for something that started in the past and continues now.',
            style: TextStyle(color: AppColors.muted, fontSize: 12.5, height: 1.35)),
      ]),
    );
  }
}

class _HowItWorks extends StatelessWidget {
  const _HowItWorks();

  @override
  Widget build(BuildContext context) {
    const steps = [
      (Icons.record_voice_over_rounded, 'Practise like the real test', 'Answer speaking questions out loud, write essays with a timer, and take full reading and listening tests.'),
      (Icons.auto_awesome_rounded, 'Get your band in minutes', 'Your AI examiner marks you on the official IELTS criteria and gives an estimated band.'),
      (Icons.trending_up_rounded, 'Fix it and improve', 'See the exact sentences to change, stronger answers, and a study plan built around your test date.'),
    ];
    return _Section(
      eyebrow: 'How it works',
      title: 'From practice to your target band',
      child: Column(children: [
        for (final (i, (icon, title, text)) in steps.indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(color: AppColors.brand, borderRadius: BorderRadius.circular(14)),
                child: Icon(icon, color: Colors.white),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${i + 1}. $title', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  const SizedBox(height: 3),
                  Text(text, style: const TextStyle(color: AppColors.muted, height: 1.4)),
                ]),
              ),
            ]),
          ),
      ]),
    );
  }
}

class _Modules extends StatelessWidget {
  const _Modules();

  @override
  Widget build(BuildContext context) {
    const modules = [
      ('speaking', Icons.mic_rounded, 'Speaking', 'Parts 1, 2 and 3 out loud, with fluency, vocabulary, grammar and pronunciation scores.'),
      ('writing', Icons.edit_rounded, 'Writing', 'Task 1 charts and letters, Task 2 essays. Corrections line by line and a model answer.'),
      ('reading', Icons.menu_book_rounded, 'Reading', 'Full 40-question tests: True/False/Not Given, matching, gap fill and more.'),
      ('listening', Icons.headphones_rounded, 'Listening', 'Real exam-style recordings with transcripts, so you see what you missed.'),
    ];
    return _Section(
      eyebrow: 'All four modules',
      title: 'Everything the exam tests',
      color: AppColors.surface,
      child: Column(children: [
        for (final (key, icon, title, text) in modules)
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(18), border: Border.all(color: AppColors.border)),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Icon(icon, color: AppColors.modules[key], size: 28),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(title, style: grotesk(size: 17)),
                  const SizedBox(height: 2),
                  Text(text, style: const TextStyle(color: AppColors.muted, height: 1.4)),
                ]),
              ),
            ]),
          ),
        const SizedBox(height: 6),
        const Text('Plus full mock exams with an overall band, and a week-by-week study plan.',
            style: TextStyle(color: AppColors.muted, fontStyle: FontStyle.italic)),
      ]),
    );
  }
}

/// Real reviews approved on the website. Hidden until there are some.
class _Reviews extends StatefulWidget {
  const _Reviews();

  @override
  State<_Reviews> createState() => _ReviewsState();
}

class _ReviewsState extends State<_Reviews> {
  List<Review> _reviews = const [];

  @override
  void initState() {
    super.initState();
    context.read<ApiClient>().get('/api/reviews').then((data) {
      final list = (data['data'] as List? ?? []).map((e) => Review.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      if (mounted) setState(() => _reviews = list);
    }).catchError((_) {});
  }

  @override
  Widget build(BuildContext context) {
    if (_reviews.isEmpty) return const SizedBox.shrink();
    return _Section(
      eyebrow: 'Reviews',
      title: 'What test takers say',
      child: SizedBox(
        height: 200,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: _reviews.length,
          separatorBuilder: (_, _) => const SizedBox(width: 12),
          itemBuilder: (context, i) {
            final r = _reviews[i];
            return Container(
              width: 280,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(color: AppColors.brand50, borderRadius: BorderRadius.circular(18)),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  for (var n = 1; n <= 5; n++) Icon(Icons.star_rounded, size: 18, color: n <= r.rating ? AppColors.accent : AppColors.border),
                ]),
                const SizedBox(height: 8),
                Expanded(child: Text('"${r.comment}"', maxLines: 5, overflow: TextOverflow.ellipsis, style: const TextStyle(height: 1.4))),
                Text(
                  [r.displayName, if (r.location != null) r.location!].join(', '),
                  style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
                ),
              ]),
            );
          },
        ),
      ),
    );
  }
}

class _Faq extends StatelessWidget {
  const _Faq();

  @override
  Widget build(BuildContext context) {
    const faqs = [
      ('Is the band score official?', 'No. It is an estimate based on the public IELTS band descriptors. Only the official test gives an official result. Our estimate shows where you stand and exactly what to fix.'),
      ('Are you connected to IELTS, the British Council or IDP?', 'No. Japa Coach is an independent practice tool and is not affiliated with IELTS, the British Council, IDP or Cambridge.'),
      ('Academic or General Training?', 'Both. Choose your test type in Settings and you get the matching Writing Task 1 and Reading papers. Speaking and Listening are the same for both.'),
      ('How long does marking take?', 'Writing, reading and listening come back within a minute or two. A full speaking mock takes a few minutes, and the app notifies you when it is ready.'),
      ('What happens to my recordings?', 'They are used only to mark your answers and are deleted automatically after 30 days. Your scores and transcripts stay in your history.'),
    ];
    return _Section(
      eyebrow: 'FAQ',
      title: 'Questions people ask',
      color: AppColors.surface,
      child: Column(children: [
        for (final (q, a) in faqs)
          Container(
            margin: const EdgeInsets.only(bottom: 10),
            decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(16), border: Border.all(color: AppColors.border)),
            child: ExpansionTile(
              shape: const Border(),
              collapsedShape: const Border(),
              title: Text(q, style: const TextStyle(fontWeight: FontWeight.w600)),
              childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              children: [Text(a, style: const TextStyle(color: AppColors.muted, height: 1.45))],
            ),
          ),
      ]),
    );
  }
}

class _FinalCta extends StatelessWidget {
  const _FinalCta();

  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.fromLTRB(12, 24, 12, 24),
        padding: const EdgeInsets.all(24),
        decoration: BoxDecoration(color: AppColors.ink, borderRadius: BorderRadius.circular(24)),
        child: Column(children: [
          Text('Your test date is coming. Start today.', textAlign: TextAlign.center, style: grotesk(size: 24, color: Colors.white)),
          const SizedBox(height: 8),
          const Text('Take a free speaking test and see your estimated band in minutes.',
              textAlign: TextAlign.center, style: TextStyle(color: Colors.white70)),
          const SizedBox(height: 18),
          SizedBox(
            width: double.infinity,
            child: FilledButton(onPressed: () => context.push('/signup'), child: const Text('Start free')),
          ),
          const SizedBox(height: 14),
          const Text('Not affiliated with IELTS, the British Council or IDP. Band scores are estimates.',
              textAlign: TextAlign.center, style: TextStyle(color: Colors.white54, fontSize: 11.5)),
        ]),
      );
}

class _BottomBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) => Container(
        padding: EdgeInsets.fromLTRB(16, 10, 16, 10 + MediaQuery.paddingOf(context).bottom),
        decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: AppColors.border))),
        child: Row(children: [
          Expanded(child: OutlinedButton(onPressed: () => context.push('/login'), child: const Text('Log in'))),
          const SizedBox(width: 10),
          Expanded(flex: 2, child: FilledButton(onPressed: () => context.push('/signup'), child: const Text('Start free'))),
        ]),
      );
}
