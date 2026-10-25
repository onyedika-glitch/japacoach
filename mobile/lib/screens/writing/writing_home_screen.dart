import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/async_loader.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';
import '../../widgets/test_type_toggle.dart';
import '../../state/auth.dart';

class WritingHomeScreen extends StatefulWidget {
  const WritingHomeScreen({super.key});

  @override
  State<WritingHomeScreen> createState() => _WritingHomeScreenState();
}

class _WritingHomeScreenState extends State<WritingHomeScreen> {
  int _taskNumber = 2;
  Key _historyKey = UniqueKey();

  @override
  Widget build(BuildContext context) {
    final api = context.read<ApiClient>();
    final testType = context.select<AuthState, String>((a) => a.user?.testType ?? 'academic');
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Writing'),
          bottom: const TabBar(tabs: [Tab(text: 'Tasks'), Tab(text: 'My essays')]),
        ),
        body: TabBarView(children: [
          ListView(padding: pagePadding(context), children: [
            const Text('Which IELTS are you taking?', style: TextStyle(fontWeight: FontWeight.w600)),
            const SizedBox(height: 6),
            const TestTypeToggle(),
            const SizedBox(height: 16),
            SegmentedButton<int>(
              segments: const [ButtonSegment(value: 1, label: Text('Task 1')), ButtonSegment(value: 2, label: Text('Task 2'))],
              selected: {_taskNumber},
              onSelectionChanged: (s) => setState(() => _taskNumber = s.first),
            ),
            const SizedBox(height: 8),
            Text(
              _taskNumber == 2
                  ? 'Write an essay of at least 250 words. About 40 minutes. Task 2 counts double.'
                  : testType == 'general'
                      ? 'Write a letter of at least 150 words. About 20 minutes.'
                      : 'Describe a chart, table or diagram in at least 150 words. About 20 minutes.',
              style: const TextStyle(color: AppColors.muted),
            ),
            const SizedBox(height: 12),
            AsyncLoader<List<WritingTask>>(
              // Reloads when the task number or the Academic/General choice changes (the server filters by it).
              key: ValueKey('$_taskNumber-$testType'),
              load: () async {
                final data = await api.get('/api/writing/tasks', query: {'task_number': _taskNumber});
                return (data['data'] as List? ?? []).map((e) => WritingTask.fromJson(Map<String, dynamic>.from(e as Map))).toList();
              },
              builder: (context, tasks, _) => Column(children: [
                if (tasks.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('No tasks yet.', style: TextStyle(color: AppColors.muted))),
                for (final t in tasks)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppCard(
                      onTap: () async {
                        await context.push('/writing/task/${t.id}');
                        if (mounted) setState(() => _historyKey = UniqueKey());
                      },
                      child: Row(children: [
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Row(children: [
                              Pill('Task ${t.taskNumber}'),
                              const SizedBox(width: 6),
                              if (t.testType != 'both') Pill(t.testType == 'academic' ? 'Academic' : 'General', color: AppColors.muted, background: const Color(0xFFF1F3F4)),
                            ]),
                            const SizedBox(height: 8),
                            Text(t.title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                            const SizedBox(height: 2),
                            Text('${t.minWords}+ words · ${t.recommendedMinutes} min', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                          ]),
                        ),
                        const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
                      ]),
                    ),
                  ),
              ]),
            ),
          ]),
          AsyncLoader<List<WritingAttempt>>(
            key: _historyKey,
            load: () async {
              final data = await api.get('/api/writing/attempts');
              return (data['data'] as List? ?? []).map((e) => WritingAttempt.fromJson(Map<String, dynamic>.from(e as Map))).toList();
            },
            builder: (context, attempts, reload) => RefreshIndicator(
              onRefresh: reload,
              child: ListView(padding: pagePadding(context), children: [
                if (attempts.isEmpty)
                  const Padding(padding: EdgeInsets.all(32), child: Text('Your marked essays will appear here.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted))),
                for (final a in attempts)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: AppCard(
                      onTap: () => context.push('/writing/report/${a.id}'),
                      child: Row(children: [
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(a.task.title, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                            const SizedBox(height: 2),
                            Text('Task ${a.task.taskNumber} · ${a.wordCount} words · ${formatDate(a.createdAt)}', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                          ]),
                        ),
                        const SizedBox(width: 10),
                        a.status == 'scored' ? BandBadge(a.overallBand) : Pill(a.status == 'failed' ? 'Failed' : 'Marking'),
                      ]),
                    ),
                  ),
              ]),
            ),
          ),
        ]),
      ),
    );
  }
}
