import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';

class StudyPlanScreen extends StatefulWidget {
  const StudyPlanScreen({super.key});

  @override
  State<StudyPlanScreen> createState() => _StudyPlanScreenState();
}

class _StudyPlanScreenState extends State<StudyPlanScreen> {
  StudyPlan? _plan;
  bool _loading = true;
  bool _generating = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await context.read<ApiClient>().get('/api/study-plan');
      setState(() => _plan = data['plan'] is Map ? StudyPlan.fromJson(Map<String, dynamic>.from(data['plan'] as Map)) : null);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _generate() async {
    setState(() {
      _generating = true;
      _error = null;
    });
    try {
      final data = await context.read<ApiClient>().post('/api/study-plan/generate', timeout: const Duration(minutes: 2));
      setState(() => _plan = StudyPlan.fromJson(Map<String, dynamic>.from(data['plan'] as Map)));
    } on ApiException catch (e) {
      if (!e.isLimitReached) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _generating = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final p = _plan;
    return Scaffold(
      appBar: AppBar(title: const Text('Study plan')),
      body: _loading
          ? const LoadingView()
          : ListView(padding: pagePadding(context), children: [
              if (_error != null) ...[
                Notice(_error!, tone: 'error'),
                if (_error!.contains('Settings')) TextButton(onPressed: () => context.push('/settings'), child: const Text('Open settings')),
                const SizedBox(height: 12),
              ],
              if (p == null)
                AppCard(
                  padding: const EdgeInsets.all(20),
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    const Icon(Icons.calendar_month_rounded, size: 40, color: AppColors.brand),
                    const SizedBox(height: 10),
                    Text('Your plan to test day', style: grotesk(size: 20)),
                    const SizedBox(height: 6),
                    const Text(
                      'We build a week-by-week plan from your latest bands, target band and test date. Set those in Settings, then generate.',
                      style: TextStyle(color: AppColors.muted, height: 1.45),
                    ),
                    const SizedBox(height: 14),
                    FilledButton(onPressed: _generating ? null : _generate, child: Text(_generating ? 'Building your plan…' : 'Generate my plan')),
                  ]),
                )
              else ...[
                AppCard(
                  color: AppColors.brand50,
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(p.summary, style: const TextStyle(height: 1.5)),
                    const SizedBox(height: 6),
                    Text('Updated ${formatDate(p.generatedAt)}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                  ]),
                ),
                for (final w in p.weeks) ...[
                  SectionTitle('Week ${w.week}: ${w.focus}'),
                  for (final d in w.days)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: AppCard(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text(d.day, style: const TextStyle(fontWeight: FontWeight.w700)),
                          const SizedBox(height: 6),
                          for (final t in d.tasks)
                            Padding(
                              padding: const EdgeInsets.only(bottom: 4),
                              child: Row(children: [
                                Container(width: 8, height: 8, decoration: BoxDecoration(color: AppColors.modules[t.module] ?? AppColors.muted, shape: BoxShape.circle)),
                                const SizedBox(width: 8),
                                Expanded(child: Text(t.title)),
                                Text('${t.minutes} min', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                              ]),
                            ),
                        ]),
                      ),
                    ),
                ],
                if (p.tips.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  BulletCard(title: 'Tips', items: p.tips, icon: Icons.tips_and_updates_outlined, color: AppColors.brand),
                ],
                const SizedBox(height: 16),
                OutlinedButton(onPressed: _generating ? null : _generate, child: Text(_generating ? 'Updating…' : 'Update my plan')),
              ],
            ]),
    );
  }
}
