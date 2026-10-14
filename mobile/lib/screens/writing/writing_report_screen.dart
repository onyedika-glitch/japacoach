import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';
import '../../widgets/polling.dart';
import '../../widgets/review_card.dart';
import 'task_visual.dart';

class WritingReportScreen extends StatefulWidget {
  const WritingReportScreen({super.key, required this.attemptId});
  final int attemptId;

  @override
  State<WritingReportScreen> createState() => _WritingReportScreenState();
}

class _WritingReportScreenState extends State<WritingReportScreen> {
  WritingAttempt? _attempt;
  String? _error;
  late final _poller = Poller(_load, () => mounted && _attempt?.status == 'processing', interval: const Duration(seconds: 4));

  @override
  void initState() {
    super.initState();
    _load().then((_) => _poller.start());
  }

  @override
  void dispose() {
    _poller.stop();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await context.read<ApiClient>().get('/api/writing/attempts/${widget.attemptId}');
      if (mounted) setState(() => _attempt = WritingAttempt.fromJson(Map<String, dynamic>.from(data['attempt'] as Map)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final a = _attempt;
    return Scaffold(
      appBar: AppBar(title: const Text('Writing report')),
      body: a == null
          ? (_error != null ? ErrorView(message: _error!, onRetry: _load) : const LoadingView(label: 'Loading report…'))
          : a.status == 'processing' || a.status == 'in_progress'
              ? ListView(padding: const EdgeInsets.all(28), children: [
                  const SizedBox(height: 40),
                  const Center(child: SizedBox(width: 64, height: 64, child: CircularProgressIndicator(strokeWidth: 5))),
                  const SizedBox(height: 24),
                  Text('Your examiner is marking…', textAlign: TextAlign.center, style: grotesk(size: 24)),
                  const SizedBox(height: 8),
                  const Text('This usually takes under a minute.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
                ])
              : a.status == 'failed'
                  ? ListView(padding: const EdgeInsets.all(28), children: [
                      const SizedBox(height: 40),
                      const Icon(Icons.error_outline_rounded, size: 52, color: AppColors.bad),
                      const SizedBox(height: 14),
                      Text("We couldn't mark this essay", textAlign: TextAlign.center, style: grotesk(size: 22)),
                      const SizedBox(height: 8),
                      const Text("Please try submitting again. This attempt won't count toward your limit.", textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
                      const SizedBox(height: 20),
                      FilledButton(onPressed: () => context.pushReplacement('/writing/task/${a.task.id}'), child: const Text('Try again')),
                    ])
                  : _report(context, a),
    );
  }

  Widget _report(BuildContext context, WritingAttempt a) {
    return ListView(padding: pagePadding(context), children: [
      AppCard(
        padding: const EdgeInsets.all(20),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [
            BandBadge(a.overallBand, large: true),
            const SizedBox(width: 16),
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Pill('Task ${a.task.taskNumber}'),
                const SizedBox(height: 6),
                Text(a.task.title, style: grotesk(size: 18)),
                Text('${a.wordCount} words · ${formatDateTime(a.createdAt)}', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
              ]),
            ),
          ]),
          if (a.summary?.isNotEmpty ?? false) ...[const SizedBox(height: 14), Text(a.summary!, style: const TextStyle(height: 1.5))],
        ]),
      ),
      const Padding(
        padding: EdgeInsets.only(top: 8),
        child: Text('Estimated band based on the public IELTS writing descriptors. Not an official result.', style: TextStyle(fontSize: 12, color: AppColors.muted)),
      ),
      if (a.scores != null) ...[const SectionTitle('Your four criteria'), CriterionGrid(a.scores!)],
      const SizedBox(height: 16),
      BulletCard(title: 'What you did well', items: a.strengths, icon: Icons.thumb_up_alt_outlined, color: AppColors.good),
      const SizedBox(height: 10),
      BulletCard(title: 'What to work on', items: a.improvements, icon: Icons.flag_outlined, color: AppColors.accent),
      const SectionTitle('Corrections'),
      CorrectionList(a.corrections),
      if (a.modelAnswer?.isNotEmpty ?? false) ...[
        const SectionTitle('Model answer'),
        AppCard(color: const Color(0xFFF3FBF5), child: Text(a.modelAnswer!, style: const TextStyle(height: 1.55))),
      ],
      const SectionTitle('Your response'),
      AppCard(child: Text(a.essay, style: const TextStyle(height: 1.55))),
      const SizedBox(height: 10),
      Card(
        child: ExpansionTile(
          shape: const Border(),
          title: const Text('Show the task again', style: TextStyle(fontWeight: FontWeight.w600)),
          childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          children: [Text(a.task.prompt, style: const TextStyle(height: 1.5)), if (a.task.visual != null) ...[const SizedBox(height: 10), TaskVisual(a.task.visual!)]],
        ),
      ),
      const SizedBox(height: 16),
      FilledButton.icon(onPressed: () => context.pushReplacement('/writing/task/${a.task.id}'), icon: const Icon(Icons.replay_rounded), label: const Text('Rewrite this task')),
      const SizedBox(height: 10),
      OutlinedButton(onPressed: () => context.go('/writing'), child: const Text('Try another task')),
      const ReviewCard(module: 'writing'),
    ]);
  }
}
