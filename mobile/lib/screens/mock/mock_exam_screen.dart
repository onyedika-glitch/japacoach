import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';
import '../../widgets/polling.dart';
import '../practice/practice_home_screen.dart';

class MockExamScreen extends StatefulWidget {
  const MockExamScreen({super.key, required this.examId});
  final int examId;

  @override
  State<MockExamScreen> createState() => _MockExamScreenState();
}

class _MockExamScreenState extends State<MockExamScreen> {
  MockExam? _exam;
  String? _error;
  String? _busy;
  late final _poller = Poller(_load, () => mounted && (_exam?.parts.values.any((p) => p.status == 'processing') ?? false), interval: const Duration(seconds: 8));

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
      final data = await context.read<ApiClient>().get('/api/mock-exams/${widget.examId}');
      if (mounted) setState(() => _exam = MockExam.fromJson(Map<String, dynamic>.from(data['exam'] as Map)));
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  Future<void> _run(String key, Future<void> Function() action) async {
    setState(() => _busy = key);
    try {
      await action();
    } on ApiException catch (e) {
      if (mounted && !e.isLimitReached) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = null);
      await _load();
      _poller.start();
    }
  }

  Future<void> _openPractice(String module, MockPart p) async {
    final attemptId = p.id('attempt_id');
    if (p.status == 'done' && attemptId != null) {
      await context.push('/practice/result/$attemptId');
    } else if (attemptId != null) {
      await context.push('/practice/test/$attemptId?mock=${widget.examId}');
    } else {
      await startPracticeTest(context, module, p.id('test_id')!, mockId: widget.examId);
    }
  }

  Future<void> _openWriting(MockPart p) async {
    final t1 = p.id('task1_attempt_id');
    final t2 = p.id('task2_attempt_id');
    if (t1 == null) {
      await context.push('/writing/task/${p.id('task1_id')}?mock=${widget.examId}');
    } else if (t2 == null) {
      await context.push('/writing/task/${p.id('task2_id')}?mock=${widget.examId}');
    } else {
      await context.push('/writing/report/$t2');
    }
  }

  Future<void> _openSpeaking(MockPart p) async {
    final attemptId = p.id('attempt_id');
    if (attemptId == null) {
      final data = await context.read<ApiClient>().post('/api/speaking/attempts', data: {'mode': 'full', 'mock_exam_id': widget.examId});
      final id = (data['attempt'] as Map)['id'];
      if (mounted) await context.push('/speaking/test/$id?mock=${widget.examId}');
    } else if (p.status == 'in_progress' || p.status == 'not_started') {
      await context.push('/speaking/test/$attemptId?mock=${widget.examId}');
    } else {
      await context.push('/speaking/report/$attemptId');
    }
  }

  @override
  Widget build(BuildContext context) {
    final e = _exam;
    return Scaffold(
      appBar: AppBar(title: const Text('Mock exam')),
      body: e == null
          ? (_error != null ? ErrorView(message: _error!, onRetry: _load) : const LoadingView())
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(padding: pagePadding(context), children: [
                AppCard(
                  color: AppColors.ink,
                  padding: const EdgeInsets.all(20),
                  child: Row(children: [
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        const Text('Overall band', style: TextStyle(color: Colors.white70)),
                        Text(band(e.overallBand), style: grotesk(size: 44, color: Colors.white)),
                        Text(
                          e.status == 'completed' ? 'All four parts marked' : 'Finish all four parts to get your overall band',
                          style: const TextStyle(color: Colors.white70),
                        ),
                      ]),
                    ),
                  ]),
                ),
                const SizedBox(height: 16),
                for (final (key, label, icon, minutes) in const [
                  ('listening', 'Listening', Icons.headphones_rounded, '30 min'),
                  ('reading', 'Reading', Icons.menu_book_rounded, '60 min'),
                  ('writing', 'Writing', Icons.edit_rounded, '60 min'),
                  ('speaking', 'Speaking', Icons.mic_rounded, '11 to 14 min'),
                ])
                  if (e.parts[key] != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _PartCard(
                        label: label,
                        icon: icon,
                        color: AppColors.modules[key]!,
                        minutes: minutes,
                        part: e.parts[key]!,
                        busy: _busy == key,
                        onTap: _busy != null
                            ? null
                            : () => _run(key, () => switch (key) {
                                  'writing' => _openWriting(e.parts[key]!),
                                  'speaking' => _openSpeaking(e.parts[key]!),
                                  _ => _openPractice(key, e.parts[key]!),
                                }),
                      ),
                    ),
              ]),
            ),
    );
  }
}

class _PartCard extends StatelessWidget {
  const _PartCard({required this.label, required this.icon, required this.color, required this.minutes, required this.part, required this.busy, this.onTap});
  final String label;
  final IconData icon;
  final Color color;
  final String minutes;
  final MockPart part;
  final bool busy;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final (statusText, action) = switch (part.status) {
      'done' => ('Marked', 'View'),
      'processing' => ('Marking…', 'View'),
      'in_progress' => ('In progress', 'Continue'),
      _ => ('Not started', 'Start'),
    };
    return AppCard(
      onTap: onTap,
      child: Row(children: [
        Container(
          width: 46,
          height: 46,
          decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(14)),
          child: Icon(icon, color: color),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(label, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            Text('$minutes · $statusText', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
          ]),
        ),
        if (busy)
          const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
        else if (part.status == 'done')
          BandBadge(part.band)
        else
          Text(action, style: const TextStyle(color: AppColors.brandDark, fontWeight: FontWeight.w700)),
      ]),
    );
  }
}
