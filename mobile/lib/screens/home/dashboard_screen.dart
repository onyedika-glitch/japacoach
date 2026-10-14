import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../state/auth.dart';
import '../../theme.dart';
import '../../widgets/async_loader.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  Key _key = UniqueKey();

  @override
  Widget build(BuildContext context) {
    final api = context.read<ApiClient>();
    final firstName = context.select<AuthState, String>((a) => a.user?.firstName ?? '');
    return Scaffold(
      appBar: AppBar(title: Text(firstName.isEmpty ? 'Dashboard' : 'Hi, $firstName')),
      body: AsyncLoader<Dashboard>(
        key: _key,
        load: () async => Dashboard.fromJson(await api.get('/api/dashboard')),
        builder: (context, d, reload) => RefreshIndicator(
          onRefresh: () async => setState(() => _key = UniqueKey()),
          child: _DashboardBody(d: d),
        ),
      ),
    );
  }
}

class _DashboardBody extends StatelessWidget {
  const _DashboardBody({required this.d});
  final Dashboard d;

  static const _modules = [
    ('speaking', 'Speaking', Icons.mic_rounded, '/speaking'),
    ('writing', 'Writing', Icons.edit_rounded, '/writing'),
    ('reading', 'Reading', Icons.menu_book_rounded, '/practice?module=reading'),
    ('listening', 'Listening', Icons.headphones_rounded, '/practice?module=listening'),
  ];

  @override
  Widget build(BuildContext context) {
    final user = d.user;
    return ListView(padding: pagePadding(context), children: [
      // Headline: overall band vs target, days to test.
      AppCard(
        color: AppColors.ink,
        padding: const EdgeInsets.all(20),
        child: Row(children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Estimated overall band', style: TextStyle(color: Colors.white70)),
              const SizedBox(height: 4),
              Text(band(d.latest['overall']), style: grotesk(size: 44, color: Colors.white)),
              Text(
                user.targetBand != null ? 'Target ${band(user.targetBand)}' : 'Set a target band in Settings',
                style: const TextStyle(color: Colors.white70),
              ),
            ]),
          ),
          Column(crossAxisAlignment: CrossAxisAlignment.end, children: [
            _Stat(icon: Icons.local_fire_department_rounded, color: AppColors.accent, text: '${user.streakDays} day streak'),
            const SizedBox(height: 8),
            _Stat(
              icon: Icons.event_rounded,
              color: AppColors.brand,
              text: d.daysToTest == null ? 'No test date' : '${d.daysToTest} days to test',
            ),
            const SizedBox(height: 8),
            _Stat(icon: Icons.workspace_premium_rounded, color: AppColors.brand100, text: '${planLabel(user.isPremium ? user.plan : 'free')} plan'),
          ]),
        ]),
      ),
      const SizedBox(height: 16),
      GridView.count(
        crossAxisCount: 2,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 1.45,
        children: [
          for (final (key, label, icon, route) in _modules)
            AppCard(
              onTap: () => context.go(route),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Icon(icon, color: AppColors.modules[key]),
                  const Spacer(),
                  const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
                ]),
                const Spacer(),
                Text(label, style: const TextStyle(color: AppColors.muted)),
                Text(band(d.latest[key]), style: grotesk(size: 26)),
              ]),
            ),
        ],
      ),
      const SectionTitle('Progress'),
      AppCard(child: d.history.isEmpty ? const _EmptyChart() : SizedBox(height: 220, child: _ProgressChart(history: d.history))),
      const SectionTitle('Usage (last 30 days)'),
      AppCard(
        child: Column(children: [
          for (final (key, label) in const [
            ('speaking_mocks', 'Full speaking mocks'),
            ('speaking_drills', 'Speaking drills'),
            ('writing', 'Writing evaluations'),
            ('reading', 'Reading tests'),
            ('listening', 'Listening tests'),
          ])
            if (d.usage[key] != null) UsageMeter(label: label, usage: d.usage[key]!),
        ]),
      ),
      if (d.recent.isNotEmpty) ...[
        const SectionTitle('Recent attempts'),
        AppCard(
          padding: EdgeInsets.zero,
          child: Column(children: [
            for (final (i, a) in d.recent.indexed) ...[
              if (i > 0) const Divider(),
              ListTile(
                leading: Icon(_icon(a.module), color: AppColors.modules[a.module]),
                title: Text(a.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w600)),
                subtitle: Text('${moduleLabels[a.module] ?? a.module} · ${formatDate(a.createdAt)}'),
                trailing: a.band != null ? BandBadge(a.band) : Pill(a.status.replaceAll('_', ' ')),
                onTap: () => context.push(_route(a)),
              ),
            ],
          ]),
        ),
      ],
      const SectionTitle('Keep going'),
      AppCard(
        onTap: () => context.push('/study-plan'),
        child: Row(children: [
          const Icon(Icons.calendar_month_rounded, color: AppColors.brand, size: 30),
          const SizedBox(width: 14),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(d.studyPlan == null ? 'Get your study plan' : 'Your study plan', style: grotesk(size: 16)),
              Text(
                d.studyPlan == null ? 'A week-by-week plan built from your results and test date.' : d.studyPlan!.summary,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.muted),
              ),
            ]),
          ),
          const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
        ]),
      ),
    ]);
  }

  static IconData _icon(String module) => switch (module) {
        'speaking' => Icons.mic_rounded,
        'writing' => Icons.edit_rounded,
        'listening' => Icons.headphones_rounded,
        _ => Icons.menu_book_rounded,
      };

  static String _route(RecentAttempt a) => switch (a.module) {
        'speaking' => a.status == 'in_progress' ? '/speaking/test/${a.id}' : '/speaking/report/${a.id}',
        'writing' => '/writing/report/${a.id}',
        _ => a.status == 'submitted' ? '/practice/result/${a.id}' : '/practice/test/${a.id}',
      };
}

class _Stat extends StatelessWidget {
  const _Stat({required this.icon, required this.color, required this.text});
  final IconData icon;
  final Color color;
  final String text;

  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(icon, color: color, size: 18),
        const SizedBox(width: 6),
        Text(text, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w600, fontSize: 13)),
      ]);
}

class _EmptyChart extends StatelessWidget {
  const _EmptyChart();

  @override
  Widget build(BuildContext context) => const Padding(
        padding: EdgeInsets.symmetric(vertical: 28),
        child: Column(children: [
          Icon(Icons.show_chart_rounded, size: 40, color: AppColors.border),
          SizedBox(height: 8),
          Text('Finish your first test to see your progress here.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted)),
        ]),
      );
}

class _ProgressChart extends StatelessWidget {
  const _ProgressChart({required this.history});
  final List<({DateTime date, String module, double band})> history;

  @override
  Widget build(BuildContext context) {
    final sorted = [...history]..sort((a, b) => a.date.compareTo(b.date));
    final start = sorted.first.date;
    double x(DateTime d) => d.difference(start).inHours / 24;
    final modules = sorted.map((h) => h.module).toSet();

    return Column(children: [
      Expanded(
        child: LineChart(LineChartData(
          minY: 3,
          maxY: 9,
          gridData: FlGridData(drawVerticalLine: false, horizontalInterval: 1, getDrawingHorizontalLine: (_) => const FlLine(color: AppColors.border, strokeWidth: 1)),
          borderData: FlBorderData(show: false),
          titlesData: FlTitlesData(
            topTitles: const AxisTitles(),
            rightTitles: const AxisTitles(),
            bottomTitles: const AxisTitles(),
            leftTitles: AxisTitles(
              sideTitles: SideTitles(showTitles: true, reservedSize: 28, interval: 1, getTitlesWidget: (v, _) => Text(v.toInt().toString(), style: const TextStyle(fontSize: 11, color: AppColors.muted))),
            ),
          ),
          lineBarsData: [
            for (final m in modules)
              LineChartBarData(
                spots: [for (final h in sorted.where((h) => h.module == m)) FlSpot(x(h.date), h.band)],
                color: AppColors.modules[m],
                barWidth: 3,
                isCurved: true,
                preventCurveOverShooting: true,
                dotData: const FlDotData(show: true),
              ),
          ],
        )),
      ),
      const SizedBox(height: 10),
      Wrap(spacing: 14, children: [
        for (final m in modules)
          Row(mainAxisSize: MainAxisSize.min, children: [
            Container(width: 10, height: 10, decoration: BoxDecoration(color: AppColors.modules[m], shape: BoxShape.circle)),
            const SizedBox(width: 5),
            Text(moduleLabels[m] ?? m, style: const TextStyle(fontSize: 12)),
          ]),
      ]),
    ]);
  }
}
