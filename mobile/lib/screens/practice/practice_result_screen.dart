import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/async_loader.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';
import '../../widgets/review_card.dart';

class PracticeResultScreen extends StatefulWidget {
  const PracticeResultScreen({super.key, required this.attemptId});
  final int attemptId;

  @override
  State<PracticeResultScreen> createState() => _PracticeResultScreenState();
}

class _PracticeResultScreenState extends State<PracticeResultScreen> {
  String _filter = 'all';

  @override
  Widget build(BuildContext context) {
    final api = context.read<ApiClient>();
    return Scaffold(
      appBar: AppBar(title: const Text('Results')),
      body: AsyncLoader<PracticeAttempt>(
        load: () async => PracticeAttempt.fromJson(Map<String, dynamic>.from((await api.get('/api/practice/attempts/${widget.attemptId}'))['attempt'] as Map)),
        builder: (context, a, _) {
          final review = {for (final r in a.review ?? <ReviewItem>[]) r.questionId: r};
          bool show(int id) => _filter == 'all' || (_filter == 'wrong' ? !(review[id]?.isCorrect ?? false) : (review[id]?.isCorrect ?? false));
          return ListView(padding: pagePadding(context), children: [
            AppCard(
              padding: const EdgeInsets.all(20),
              child: Row(children: [
                BandBadge(a.band, large: true),
                const SizedBox(width: 16),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(a.testTitle, style: grotesk(size: 18)),
                    Text(formatDateTime(a.submittedAt), style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                    const SizedBox(height: 6),
                    Text.rich(TextSpan(children: [
                      TextSpan(text: '${a.correctCount ?? 0}', style: grotesk(size: 26)),
                      TextSpan(text: ' / ${a.totalCount} correct', style: const TextStyle(color: AppColors.muted)),
                    ])),
                  ]),
                ),
              ]),
            ),
            const Padding(
              padding: EdgeInsets.only(top: 8),
              child: Text('Band estimated from your raw score using standard IELTS conversion tables, scaled to 40 questions.',
                  style: TextStyle(fontSize: 12, color: AppColors.muted)),
            ),
            const SizedBox(height: 16),
            SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'all', label: Text('All')),
                ButtonSegment(value: 'wrong', label: Text('Wrong')),
                ButtonSegment(value: 'right', label: Text('Correct')),
              ],
              selected: {_filter},
              onSelectionChanged: (v) => setState(() => _filter = v.first),
            ),
            for (final s in a.sections) ...[
              SectionTitle(s.title),
              if (s.transcript.isNotEmpty)
                Card(
                  child: ExpansionTile(
                    shape: const Border(),
                    title: const Text('Show transcript', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    children: [
                      for (final line in s.transcript)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: Text.rich(TextSpan(children: [
                            TextSpan(text: '${line.speaker}: ', style: const TextStyle(fontWeight: FontWeight.w700, color: AppColors.brandDark)),
                            TextSpan(text: line.text),
                          ])),
                        ),
                    ],
                  ),
                )
              else if (s.body?.isNotEmpty ?? false)
                Card(
                  child: ExpansionTile(
                    shape: const Border(),
                    title: const Text('Show passage', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 14)),
                    childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                    children: [Text(s.body!, style: const TextStyle(height: 1.55))],
                  ),
                ),
              const SizedBox(height: 10),
              for (final q in s.questions)
                if (show(q.id)) _ReviewRow(question: q, review: review[q.id]),
            ],
            const SizedBox(height: 16),
            FilledButton(onPressed: () => context.go('/practice?module=${a.module}'), child: const Text('Try another test')),
            const SizedBox(height: 10),
            OutlinedButton(onPressed: () => context.go('/home'), child: const Text('Back to dashboard')),
            ReviewCard(module: a.module),
          ]);
        },
      ),
    );
  }
}

class _ReviewRow extends StatelessWidget {
  const _ReviewRow({required this.question, required this.review});
  final PracticeQuestion question;
  final ReviewItem? review;

  @override
  Widget build(BuildContext context) {
    final correct = review?.isCorrect ?? false;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: (correct ? AppColors.good : AppColors.bad).withValues(alpha: 0.3)),
        ),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(correct ? Icons.check_circle_rounded : Icons.cancel_rounded, color: correct ? AppColors.good : AppColors.bad, size: 20),
            const SizedBox(width: 8),
            Expanded(child: Text('${question.number}. ${question.prompt}', style: const TextStyle(height: 1.4))),
          ]),
          const SizedBox(height: 8),
          Text('Your answer: ${(review?.yourAnswer?.isNotEmpty ?? false) ? review!.yourAnswer : 'No answer'}',
              style: TextStyle(color: correct ? AppColors.good : AppColors.bad, fontWeight: FontWeight.w600)),
          if (!correct && (review?.correctAnswers.isNotEmpty ?? false))
            Text('Correct: ${review!.correctAnswers.join(' / ')}', style: const TextStyle(color: AppColors.good, fontWeight: FontWeight.w600)),
          if (review?.explanation?.isNotEmpty ?? false) ...[
            const SizedBox(height: 6),
            Text(review!.explanation!, style: const TextStyle(color: AppColors.muted, fontSize: 14, height: 1.4)),
          ],
        ]),
      ),
    );
  }
}
