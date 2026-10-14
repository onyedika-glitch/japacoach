import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/audio_button.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';
import '../../widgets/polling.dart';
import '../../widgets/review_card.dart';

class SpeakingReportScreen extends StatefulWidget {
  const SpeakingReportScreen({super.key, required this.attemptId});
  final int attemptId;

  @override
  State<SpeakingReportScreen> createState() => _SpeakingReportScreenState();
}

class _SpeakingReportScreenState extends State<SpeakingReportScreen> {
  SpeakingAttempt? _attempt;
  String? _error;
  late final _poller = Poller(_load, () => mounted && _attempt?.status == 'processing');

  @override
  void initState() {
    super.initState();
    _load().then((_) => _poller.start(), onError: (_) {});
  }

  @override
  void dispose() {
    _poller.stop();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await context.read<ApiClient>().get('/api/speaking/attempts/${widget.attemptId}');
      if (mounted) setState(() => _attempt = SpeakingAttempt.fromJson(Map<String, dynamic>.from(data['attempt'] as Map)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final a = _attempt;
    return Scaffold(
      appBar: AppBar(title: const Text('Speaking report')),
      body: a == null
          ? (_error != null ? ErrorView(message: _error!, onRetry: _load) : const LoadingView(label: 'Loading report…'))
          : switch (a.status) {
              'processing' || 'in_progress' => _Processing(attempt: a),
              'failed' => const _Failed(),
              _ => _Report(attempt: a),
            },
    );
  }
}

class _Processing extends StatelessWidget {
  const _Processing({required this.attempt});
  final SpeakingAttempt attempt;

  @override
  Widget build(BuildContext context) {
    final done = attempt.questions.where((q) => q.answer?.status == 'transcribed').length;
    return ListView(padding: const EdgeInsets.all(28), children: [
      const SizedBox(height: 40),
      const Center(child: SizedBox(width: 64, height: 64, child: CircularProgressIndicator(strokeWidth: 5))),
      const SizedBox(height: 24),
      Text('Your examiner is marking…', textAlign: TextAlign.center, style: grotesk(size: 24)),
      const SizedBox(height: 10),
      const Text(
        "We're transcribing every answer and checking fluency, vocabulary, grammar and pronunciation. A full mock can take a few minutes. "
        "You can leave this page; we'll email you when it's ready.",
        textAlign: TextAlign.center,
        style: TextStyle(color: AppColors.muted, height: 1.45),
      ),
      const SizedBox(height: 16),
      Text('$done of ${attempt.questions.length} answers transcribed', textAlign: TextAlign.center, style: const TextStyle(fontWeight: FontWeight.w600)),
      if (attempt.status == 'in_progress') ...[
        const SizedBox(height: 20),
        FilledButton(onPressed: () => context.pushReplacement('/speaking/test/${attempt.id}'), child: const Text('Continue test')),
      ],
    ]);
  }
}

class _Failed extends StatelessWidget {
  const _Failed();

  @override
  Widget build(BuildContext context) => ListView(padding: const EdgeInsets.all(28), children: [
        const SizedBox(height: 40),
        const Icon(Icons.error_outline_rounded, size: 52, color: AppColors.bad),
        const SizedBox(height: 14),
        Text("We couldn't mark this test", textAlign: TextAlign.center, style: grotesk(size: 22)),
        const SizedBox(height: 8),
        const Text("Something went wrong while processing your audio. This attempt won't count toward your limit. Please try again.",
            textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
        const SizedBox(height: 20),
        FilledButton(onPressed: () => context.go('/speaking'), child: const Text('Start a new test')),
      ]);
}

class _Report extends StatelessWidget {
  const _Report({required this.attempt});
  final SpeakingAttempt attempt;

  @override
  Widget build(BuildContext context) {
    final f = attempt.feedback;
    final m = f?.metrics;
    return ListView(padding: pagePadding(context), children: [
      AppCard(
        padding: const EdgeInsets.all(20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            BandBadge(attempt.overallBand, large: true),
            const SizedBox(width: 16),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Estimated band', style: TextStyle(color: AppColors.muted)),
                Text(attempt.modeTitle, style: grotesk(size: 20)),
                Text(formatDateTime(attempt.scoredAt ?? attempt.createdAt), style: const TextStyle(color: AppColors.muted, fontSize: 13)),
              ]),
            ),
          ]),
          if (f?.summary.isNotEmpty ?? false) ...[const SizedBox(height: 14), Text(f!.summary, style: const TextStyle(height: 1.5))],
        ]),
      ),
      const Padding(
        padding: EdgeInsets.only(top: 8),
        child: Text('Estimated band based on the public IELTS speaking descriptors. Not an official result.', style: TextStyle(fontSize: 12, color: AppColors.muted)),
      ),
      if (attempt.scores != null) ...[const SectionTitle('Your four criteria'), CriterionGrid(attempt.scores!)],
      if (m != null) ...[
        const SectionTitle('Your delivery'),
        GridView.count(
          crossAxisCount: 2,
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          mainAxisSpacing: 10,
          crossAxisSpacing: 10,
          childAspectRatio: 2.1,
          children: [
            _Metric('Words per minute', '${(m['words_per_minute'] as num? ?? 0).round()}'),
            _Metric('Long pauses', '${m['long_pauses'] ?? 0}'),
            _Metric('Filler words', '${m['filler_words'] ?? 0}'),
            _Metric('Speaking time', clock(m['total_speaking_seconds'] as num? ?? 0)),
          ],
        ),
      ],
      if (f != null) ...[
        const SizedBox(height: 16),
        BulletCard(title: 'What you did well', items: f.strengths, icon: Icons.thumb_up_alt_outlined, color: AppColors.good),
        const SizedBox(height: 10),
        BulletCard(title: 'What to work on', items: f.improvements, icon: Icons.flag_outlined, color: AppColors.accent),
      ],
      if (f?.corrections.isNotEmpty ?? false) ...[const SectionTitle('Fix these sentences'), CorrectionList(f!.corrections)],
      if (f?.pronunciationTips.isNotEmpty ?? false) ...[
        const SectionTitle('Pronunciation tips'),
        for (final t in f!.pronunciationTips)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(t.word, style: const TextStyle(fontWeight: FontWeight.w700)),
                const SizedBox(height: 4),
                Text(t.tip, style: const TextStyle(color: AppColors.muted)),
              ]),
            ),
          ),
      ],
      if (f?.betterAnswers.isNotEmpty ?? false) ...[
        const SectionTitle('Stronger versions of your answers'),
        for (final (i, b) in f!.betterAnswers.indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: Card(
              child: ExpansionTile(
                initiallyExpanded: i == 0,
                shape: const Border(),
                title: Text(b.question, style: const TextStyle(fontWeight: FontWeight.w600)),
                childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                children: [
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(color: AppColors.good.withValues(alpha: 0.06), borderRadius: BorderRadius.circular(12)),
                    child: Text(b.answer, style: const TextStyle(height: 1.5)),
                  ),
                ],
              ),
            ),
          ),
      ],
      if (attempt.questions.isNotEmpty) ...[
        const SectionTitle('Your answers'),
        for (final q in attempt.questions)
          Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('Part ${q.part}', style: const TextStyle(color: AppColors.brandDark, fontWeight: FontWeight.w700, fontSize: 12)),
                const SizedBox(height: 2),
                Text(q.prompt, style: const TextStyle(fontWeight: FontWeight.w600)),
                if (q.answer?.audioUrl != null) ...[const SizedBox(height: 10), AudioPlayerBar(url: q.answer!.audioUrl!)],
                const SizedBox(height: 8),
                Text(
                  q.answer?.transcript != null ? '"${q.answer!.transcript}"' : 'No transcript.',
                  style: TextStyle(color: AppColors.muted, height: 1.45, fontStyle: q.answer?.transcript == null ? FontStyle.italic : null),
                ),
              ]),
            ),
          ),
      ],
      const SizedBox(height: 16),
      FilledButton.icon(onPressed: () => context.go('/speaking'), icon: const Icon(Icons.replay_rounded), label: const Text('Practise again')),
      const SizedBox(height: 10),
      OutlinedButton(onPressed: () => context.push('/study-plan'), child: const Text('Update my study plan')),
      const ReviewCard(module: 'speaking'),
    ]);
  }
}

class _Metric extends StatelessWidget {
  const _Metric(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => AppCard(
        padding: const EdgeInsets.all(12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisAlignment: MainAxisAlignment.center, children: [
          Text(label, style: const TextStyle(color: AppColors.muted, fontSize: 12)),
          Text(value, style: grotesk(size: 22)),
        ]),
      );
}
