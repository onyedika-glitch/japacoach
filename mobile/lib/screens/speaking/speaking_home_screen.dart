import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/async_loader.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';

class SpeakingHomeScreen extends StatefulWidget {
  const SpeakingHomeScreen({super.key});

  @override
  State<SpeakingHomeScreen> createState() => _SpeakingHomeScreenState();
}

class _SpeakingHomeScreenState extends State<SpeakingHomeScreen> {
  Key _key = UniqueKey();
  String? _starting;

  static const _modes = [
    ('full', 'Full speaking mock', 'All 3 parts, about 11 to 14 minutes. Scored like the real test.', Icons.record_voice_over_rounded),
    ('part1', 'Part 1 drill', 'Short questions about you and familiar topics.', Icons.chat_bubble_outline_rounded),
    ('part2', 'Part 2 drill', 'Cue card: 1 minute to prepare, then speak for up to 2 minutes.', Icons.style_rounded),
    ('part3', 'Part 3 drill', 'Discussion questions. Give opinions, reasons and examples.', Icons.forum_outlined),
  ];

  Future<void> _start(String mode) async {
    setState(() => _starting = mode);
    try {
      final data = await context.read<ApiClient>().post('/api/speaking/attempts', data: {'mode': mode});
      final attempt = SpeakingAttempt.fromJson(Map<String, dynamic>.from(data['attempt'] as Map));
      if (!mounted) return;
      await context.push('/speaking/test/${attempt.id}');
      if (mounted) setState(() => _key = UniqueKey());
    } on ApiException catch (e) {
      if (mounted && !e.isLimitReached) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _starting = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final api = context.read<ApiClient>();
    return Scaffold(
      appBar: AppBar(title: const Text('Speaking')),
      body: RefreshIndicator(
        onRefresh: () async => setState(() => _key = UniqueKey()),
        child: ListView(padding: pagePadding(context), children: [
          const Text('Answer out loud. Your examiner listens, then marks fluency, vocabulary, grammar and pronunciation.',
              style: TextStyle(color: AppColors.muted, height: 1.4)),
          const SizedBox(height: 16),
          for (final (mode, title, text, icon) in _modes)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: AppCard(
                onTap: _starting == null ? () => _start(mode) : null,
                color: mode == 'full' ? AppColors.brand50 : null,
                child: Row(children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(color: mode == 'full' ? AppColors.brand : AppColors.brand100, borderRadius: BorderRadius.circular(14)),
                    child: Icon(icon, color: mode == 'full' ? Colors.white : AppColors.brandDark),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(title, style: grotesk(size: 16)),
                      const SizedBox(height: 2),
                      Text(text, style: const TextStyle(color: AppColors.muted, fontSize: 14, height: 1.35)),
                    ]),
                  ),
                  _starting == mode
                      ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                      : const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
                ]),
              ),
            ),
          const SectionTitle('Your history'),
          AsyncLoader<List<SpeakingAttempt>>(
            key: _key,
            load: () async {
              final data = await api.get('/api/speaking/attempts');
              return (data['data'] as List? ?? []).map((e) => SpeakingAttempt.fromJson(Map<String, dynamic>.from(e as Map))).toList();
            },
            builder: (context, attempts, _) => attempts.isEmpty
                ? const Padding(
                    padding: EdgeInsets.symmetric(vertical: 20),
                    child: Text('No speaking tests yet. Start with a Part 1 drill.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
                  )
                : AppCard(
                    padding: EdgeInsets.zero,
                    child: Column(children: [
                      for (final (i, a) in attempts.indexed) ...[
                        if (i > 0) const Divider(),
                        ListTile(
                          title: Text(a.modeTitle, style: const TextStyle(fontWeight: FontWeight.w600)),
                          subtitle: Text(formatDateTime(a.createdAt)),
                          trailing: a.status == 'scored' ? BandBadge(a.overallBand) : Pill(_statusLabel(a.status)),
                          onTap: () async {
                            await context.push(a.status == 'in_progress' ? '/speaking/test/${a.id}' : '/speaking/report/${a.id}');
                            if (mounted) setState(() => _key = UniqueKey());
                          },
                        ),
                      ],
                    ]),
                  ),
          ),
        ]),
      ),
    );
  }

  static String _statusLabel(String s) => const {'in_progress': 'Continue', 'processing': 'Marking', 'failed': 'Failed'}[s] ?? s;
}
