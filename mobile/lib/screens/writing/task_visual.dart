import 'dart:math' as math;

import 'package:fl_chart/fl_chart.dart';
import 'package:flutter/material.dart';

import '../../theme.dart';

const _palette = [Color(0xFF01B8C6), Color(0xFFF5A524), Color(0xFF6366F1), Color(0xFFEC4899), Color(0xFF16A34A), Color(0xFF64748B)];

/// Renders a Writing Task 1 chart or table from the API's visual description.
class TaskVisual extends StatelessWidget {
  const TaskVisual(this.visual, {super.key});
  final Map<String, dynamic> visual;

  @override
  Widget build(BuildContext context) {
    final type = visual['type']?.toString();
    final title = visual['title']?.toString() ?? '';
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (title.isNotEmpty) Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
          if (visual['unit'] != null) Text('Unit: ${visual['unit']}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
          const SizedBox(height: 12),
          if (type == 'table') _table() else ..._chart(type),
        ]),
      ),
    );
  }

  Widget _table() {
    final columns = (visual['columns'] as List? ?? []).map((e) => e.toString()).toList();
    final rows = (visual['rows'] as List? ?? []).map((r) => (r as List).map((c) => c.toString()).toList()).toList();
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: DataTable(
        headingRowHeight: 40,
        dataRowMinHeight: 36,
        dataRowMaxHeight: 44,
        columnSpacing: 20,
        columns: [for (final c in columns) DataColumn(label: Text(c, style: const TextStyle(fontWeight: FontWeight.w700)))],
        rows: [for (final r in rows) DataRow(cells: [for (var i = 0; i < columns.length; i++) DataCell(Text(i < r.length ? r[i] : ''))])],
      ),
    );
  }

  List<Widget> _chart(String? type) {
    final labels = (visual['labels'] as List? ?? []).map((e) => e.toString()).toList();
    final datasets = (visual['datasets'] as List? ?? [])
        .map((d) => (label: (d as Map)['label']?.toString() ?? '', data: (d['data'] as List? ?? []).map((v) => (v as num).toDouble()).toList()))
        .toList();
    if (labels.isEmpty || datasets.isEmpty) return [const Text('Chart unavailable.')];
    final maxY = datasets.expand((d) => d.data).fold<double>(0, math.max) * 1.15;

    Widget bottomTitle(double v, TitleMeta _) {
      final i = v.round();
      if (i < 0 || i >= labels.length || (v - i).abs() > 0.01) return const SizedBox.shrink();
      return Padding(padding: const EdgeInsets.only(top: 6), child: Text(labels[i], style: const TextStyle(fontSize: 10)));
    }

    final titles = FlTitlesData(
      topTitles: const AxisTitles(),
      rightTitles: const AxisTitles(),
      bottomTitles: AxisTitles(sideTitles: SideTitles(showTitles: true, reservedSize: 28, interval: 1, getTitlesWidget: bottomTitle)),
      leftTitles: AxisTitles(
        sideTitles: SideTitles(showTitles: true, reservedSize: 40, getTitlesWidget: (v, m) => Text(m.formattedValue, style: const TextStyle(fontSize: 10))),
      ),
    );

    final Widget chart = switch (type) {
      'pie' => PieChart(PieChartData(sectionsSpace: 2, centerSpaceRadius: 0, sections: [
          for (final (i, v) in datasets.first.data.indexed)
            PieChartSectionData(
              value: v,
              color: _palette[i % _palette.length],
              radius: 90,
              title: v.toStringAsFixed(v % 1 == 0 ? 0 : 1),
              titleStyle: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 12),
            ),
        ])),
      'line' => LineChart(LineChartData(
          minY: 0,
          maxY: maxY,
          titlesData: titles,
          borderData: FlBorderData(show: false),
          gridData: const FlGridData(drawVerticalLine: false),
          lineBarsData: [
            for (final (i, d) in datasets.indexed)
              LineChartBarData(
                spots: [for (final (x, y) in d.data.indexed) FlSpot(x.toDouble(), y)],
                color: _palette[i % _palette.length],
                barWidth: 3,
              ),
          ],
        )),
      _ => BarChart(BarChartData(
          maxY: maxY,
          titlesData: titles,
          borderData: FlBorderData(show: false),
          gridData: const FlGridData(drawVerticalLine: false),
          barGroups: [
            for (var x = 0; x < labels.length; x++)
              BarChartGroupData(x: x, barsSpace: 2, barRods: [
                for (final (i, d) in datasets.indexed)
                  BarChartRodData(
                    toY: x < d.data.length ? d.data[x] : 0,
                    color: _palette[i % _palette.length],
                    width: math.max(4, 40 / (labels.length * datasets.length)),
                    borderRadius: BorderRadius.circular(2),
                  ),
              ]),
          ],
        )),
    };

    final legend = type == 'pie'
        ? [for (final (i, l) in labels.indexed) (l, _palette[i % _palette.length])]
        : datasets.length > 1
            ? [for (final (i, d) in datasets.indexed) (d.label, _palette[i % _palette.length])]
            : <(String, Color)>[];

    return [
      SizedBox(height: 220, child: chart),
      if (legend.isNotEmpty) ...[
        const SizedBox(height: 10),
        Wrap(spacing: 12, runSpacing: 6, children: [
          for (final (label, color) in legend)
            Row(mainAxisSize: MainAxisSize.min, children: [
              Container(width: 10, height: 10, color: color),
              const SizedBox(width: 5),
              Text(label, style: const TextStyle(fontSize: 12)),
            ]),
        ]),
      ],
    ];
  }
}
