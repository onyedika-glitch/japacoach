import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/async_loader.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';

/// Starts a reading or listening test and opens it. Shared with the mock exam screen.
Future<void> startPracticeTest(BuildContext context, String module, int testId, {int? mockId}) async {
  final data = await context.read<ApiClient>().post('/api/practice/$module/tests/$testId/attempts', data: {'mock_exam_id': ?mockId});
  final id = (data['attempt'] as Map)['id'];
  if (context.mounted) await context.push('/practice/test/$id${mockId != null ? '?mock=$mockId' : ''}');
}

class PracticeHomeScreen extends StatefulWidget {
  const PracticeHomeScreen({super.key, this.initialModule});
  final String? initialModule;

  @override
  State<PracticeHomeScreen> createState() => _PracticeHomeScreenState();
}

class _PracticeHomeScreenState extends State<PracticeHomeScreen> with SingleTickerProviderStateMixin {
  late final _tabs = TabController(length: 2, vsync: this, initialIndex: widget.initialModule == 'listening' ? 1 : 0);

  @override
  void didUpdateWidget(covariant PracticeHomeScreen old) {
    super.didUpdateWidget(old);
    if (widget.initialModule != null) _tabs.animateTo(widget.initialModule == 'listening' ? 1 : 0);
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Practice tests'),
        bottom: TabBar(controller: _tabs, tabs: const [Tab(text: 'Reading'), Tab(text: 'Listening')]),
      ),
      body: TabBarView(controller: _tabs, children: const [_TestList(module: 'reading'), _TestList(module: 'listening')]),
    );
  }
}

class _TestList extends StatefulWidget {
  const _TestList({required this.module});
  final String module;

  @override
  State<_TestList> createState() => _TestListState();
}

class _TestListState extends State<_TestList> with AutomaticKeepAliveClientMixin {
  Key _key = UniqueKey();
  int? _starting;

  @override
  bool get wantKeepAlive => true;

  Future<void> _start(PracticeTestSummary t) async {
    setState(() => _starting = t.id);
    try {
      await startPracticeTest(context, widget.module, t.id);
      if (mounted) setState(() => _key = UniqueKey());
    } on ApiException catch (e) {
      if (mounted && !e.isLimitReached) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _starting = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    super.build(context);
    final api = context.read<ApiClient>();
    final listening = widget.module == 'listening';
    return AsyncLoader<List<PracticeTestSummary>>(
      key: _key,
      load: () async {
        final data = await api.get('/api/practice/${widget.module}/tests');
        return (data['data'] as List? ?? []).map((e) => PracticeTestSummary.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      },
      builder: (context, tests, reload) => RefreshIndicator(
        onRefresh: reload,
        child: ListView(padding: pagePadding(context), children: [
          Text(
            listening
                ? 'Four recorded sections, 40 questions. Use headphones and answer while you listen.'
                : 'Three passages, 40 questions, 60 minutes. Answers save automatically.',
            style: const TextStyle(color: AppColors.muted, height: 1.4),
          ),
          const SizedBox(height: 14),
          if (tests.isEmpty) const Padding(padding: EdgeInsets.all(24), child: Text('No tests yet.', textAlign: TextAlign.center)),
          for (final t in tests)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: AppCard(
                onTap: _starting == null ? () => _start(t) : null,
                child: Row(children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(color: AppColors.modules[widget.module]!.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(14)),
                    child: Icon(listening ? Icons.headphones_rounded : Icons.menu_book_rounded, color: AppColors.modules[widget.module]),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(t.title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                      const SizedBox(height: 2),
                      Text(
                        '${t.questionCount} questions · ${t.durationMinutes} min${t.attemptsCount > 0 ? ' · Best ${band(t.bestBand)}' : ''}',
                        style: const TextStyle(color: AppColors.muted, fontSize: 13),
                      ),
                    ]),
                  ),
                  _starting == t.id
                      ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                      : const Icon(Icons.play_circle_fill_rounded, color: AppColors.brand, size: 32),
                ]),
              ),
            ),
        ]),
      ),
    );
  }
}
