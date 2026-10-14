import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/async_loader.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';

class MockListScreen extends StatefulWidget {
  const MockListScreen({super.key});

  @override
  State<MockListScreen> createState() => _MockListScreenState();
}

class _MockListScreenState extends State<MockListScreen> {
  Key _key = UniqueKey();
  bool _creating = false;

  Future<void> _create() async {
    setState(() => _creating = true);
    try {
      final data = await context.read<ApiClient>().post('/api/mock-exams');
      final id = (data['exam'] as Map)['id'];
      if (!mounted) return;
      await context.push('/mock/$id');
      if (mounted) setState(() => _key = UniqueKey());
    } on ApiException catch (e) {
      if (mounted && !e.isLimitReached) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _creating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final api = context.read<ApiClient>();
    return Scaffold(
      appBar: AppBar(title: const Text('Full mock exam')),
      body: ListView(padding: pagePadding(context), children: [
        AppCard(
          color: AppColors.brand50,
          padding: const EdgeInsets.all(20),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('All four modules, one overall band', style: grotesk(size: 20)),
            const SizedBox(height: 8),
            const Text(
              'Listening, Reading, Writing (Task 1 and 2) and Speaking, like test day. Take the parts in any order; your overall band appears when all four are marked.',
              style: TextStyle(color: AppColors.muted, height: 1.45),
            ),
            const SizedBox(height: 14),
            FilledButton(onPressed: _creating ? null : _create, child: Text(_creating ? 'Setting up…' : 'Start a new mock exam')),
          ]),
        ),
        const SectionTitle('Your mock exams'),
        AsyncLoader<List<MockExam>>(
          key: _key,
          load: () async {
            final data = await api.get('/api/mock-exams');
            return (data['data'] as List? ?? []).map((e) => MockExam.fromJson(Map<String, dynamic>.from(e as Map))).toList();
          },
          builder: (context, exams, _) => Column(children: [
            if (exams.isEmpty) const Padding(padding: EdgeInsets.all(20), child: Text('No mock exams yet.', style: TextStyle(color: AppColors.muted))),
            for (final e in exams)
              Padding(
                padding: const EdgeInsets.only(bottom: 10),
                child: AppCard(
                  onTap: () async {
                    await context.push('/mock/${e.id}');
                    if (mounted) setState(() => _key = UniqueKey());
                  },
                  child: Row(children: [
                    Expanded(
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('Mock exam · ${e.testType == 'general' ? 'General' : 'Academic'}', style: const TextStyle(fontWeight: FontWeight.w700)),
                        Text('${formatDate(e.createdAt)} · ${e.parts.values.where((p) => p.status == 'done').length}/4 parts done',
                            style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                      ]),
                    ),
                    e.status == 'completed' ? BandBadge(e.overallBand) : const Pill('In progress'),
                  ]),
                ),
              ),
          ]),
        ),
      ]),
    );
  }
}
