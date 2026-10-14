import 'package:flutter/material.dart';

import '../models/models.dart';
import '../theme.dart';
import 'format.dart';

class LoadingView extends StatelessWidget {
  const LoadingView({super.key, this.label});
  final String? label;

  @override
  Widget build(BuildContext context) => Center(
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const CircularProgressIndicator(),
          if (label != null) ...[const SizedBox(height: 14), Text(label!, style: const TextStyle(color: AppColors.muted))],
        ]),
      );
}

class ErrorView extends StatelessWidget {
  const ErrorView({super.key, required this.message, this.onRetry});
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_rounded, size: 44, color: AppColors.muted),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            if (onRetry != null) ...[const SizedBox(height: 16), OutlinedButton(onPressed: onRetry, child: const Text('Try again'))],
          ]),
        ),
      );
}

class Notice extends StatelessWidget {
  const Notice(this.text, {super.key, this.tone = 'info'});
  final String text;
  final String tone;

  @override
  Widget build(BuildContext context) {
    final (color, icon) = switch (tone) {
      'error' => (AppColors.bad, Icons.error_outline),
      'success' => (AppColors.good, Icons.check_circle_outline),
      'warning' => (AppColors.accent, Icons.warning_amber_rounded),
      _ => (AppColors.brand, Icons.info_outline),
    };
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Icon(icon, size: 20, color: color),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: const TextStyle(fontSize: 14))),
      ]),
    );
  }
}

class SectionTitle extends StatelessWidget {
  const SectionTitle(this.text, {super.key, this.trailing});
  final String text;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 24, bottom: 10),
        child: Row(children: [
          Expanded(child: Text(text, style: grotesk(size: 18))),
          ?trailing,
        ]),
      );
}

class AppCard extends StatelessWidget {
  const AppCard({super.key, required this.child, this.padding = const EdgeInsets.all(16), this.onTap, this.color});
  final Widget child;
  final EdgeInsets padding;
  final VoidCallback? onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) => Card(
        color: color,
        clipBehavior: Clip.antiAlias,
        child: InkWell(onTap: onTap, child: Padding(padding: padding, child: child)),
      );
}

class BandBadge extends StatelessWidget {
  const BandBadge(this.value, {super.key, this.large = false});
  final double? value;
  final bool large;

  @override
  Widget build(BuildContext context) {
    final v = value;
    final (bg, fg) = v == null
        ? (const Color(0xFFF3F4F6), AppColors.muted)
        : v >= 7
            ? (AppColors.good.withValues(alpha: 0.1), AppColors.good)
            : v >= 5.5
                ? (AppColors.brand50, AppColors.brandDark)
                : (AppColors.accent.withValues(alpha: 0.15), const Color(0xFFB4730A));
    return Container(
      constraints: BoxConstraints(minWidth: large ? 96 : 52),
      padding: EdgeInsets.symmetric(horizontal: large ? 16 : 10, vertical: large ? 10 : 6),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(large ? 18 : 10)),
      child: Text(band(v), textAlign: TextAlign.center, style: grotesk(size: large ? 44 : 18, color: fg)),
    );
  }
}

class CriterionGrid extends StatelessWidget {
  const CriterionGrid(this.scores, {super.key});
  final Map<String, CriterionScore> scores;

  @override
  Widget build(BuildContext context) => Column(
        children: [
          for (final e in scores.entries)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: AppCard(
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(criterionLabels[e.key] ?? e.key, style: const TextStyle(fontWeight: FontWeight.w700)),
                      if (e.value.comment.isNotEmpty) ...[
                        const SizedBox(height: 4),
                        Text(e.value.comment, style: const TextStyle(color: AppColors.muted, fontSize: 14, height: 1.4)),
                      ],
                    ]),
                  ),
                  const SizedBox(width: 12),
                  BandBadge(e.value.band),
                ]),
              ),
            ),
        ],
      );
}

class BulletCard extends StatelessWidget {
  const BulletCard({super.key, required this.title, required this.items, required this.icon, required this.color});
  final String title;
  final List<String> items;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) => AppCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(children: [Icon(icon, color: color, size: 20), const SizedBox(width: 8), Text(title, style: grotesk(size: 16))]),
          const SizedBox(height: 10),
          for (final item in items)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Container(margin: const EdgeInsets.only(top: 7, right: 10), width: 6, height: 6, decoration: BoxDecoration(color: color, shape: BoxShape.circle)),
                Expanded(child: Text(item, style: const TextStyle(height: 1.45))),
              ]),
            ),
        ]),
      );
}

class CorrectionList extends StatelessWidget {
  const CorrectionList(this.corrections, {super.key});
  final List<Correction> corrections;

  @override
  Widget build(BuildContext context) {
    if (corrections.isEmpty) return const Text('No corrections needed.', style: TextStyle(color: AppColors.muted));
    return Column(children: [
      for (final c in corrections)
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: AppCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('"${c.original}"',
                  style: TextStyle(color: AppColors.bad.withValues(alpha: 0.8), decoration: TextDecoration.lineThrough, height: 1.4)),
              const SizedBox(height: 4),
              Text('"${c.corrected}"', style: const TextStyle(color: AppColors.good, fontWeight: FontWeight.w600, height: 1.4)),
              if (c.explanation.isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(c.explanation, style: const TextStyle(color: AppColors.muted, fontSize: 14, height: 1.4)),
              ],
            ]),
          ),
        ),
    ]);
  }
}

class UsageMeter extends StatelessWidget {
  const UsageMeter({super.key, required this.label, required this.usage});
  final String label;
  final Usage usage;

  @override
  Widget build(BuildContext context) {
    final limit = usage.limit;
    final pct = limit == null ? 0.08 : (limit == 0 ? 1.0 : (usage.used / limit).clamp(0.0, 1.0));
    final full = limit != null && usage.used >= limit;
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(label, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 14))),
          Text(limit == null ? '${usage.used} used · unlimited' : '${usage.used} / $limit', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
        ]),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(8),
          child: LinearProgressIndicator(
            value: pct.toDouble(),
            minHeight: 8,
            backgroundColor: const Color(0xFFF1F3F4),
            color: full ? AppColors.accent : AppColors.brand,
          ),
        ),
      ]),
    );
  }
}

/// A small pill label.
class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.color = AppColors.brandDark, this.background = AppColors.brand50});
  final String text;
  final Color color;
  final Color background;

  // The Row keeps the pill its natural width even inside full-width parents like ListView.
  @override
  Widget build(BuildContext context) => Row(mainAxisSize: MainAxisSize.min, children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(color: background, borderRadius: BorderRadius.circular(20)),
          child: Text(text, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w700)),
        ),
      ]);
}

void showSnack(BuildContext context, String message) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(SnackBar(content: Text(message), behavior: SnackBarBehavior.floating));
}

/// Page padding that leaves room for the system navigation bar.
EdgeInsets pagePadding(BuildContext context) => EdgeInsets.fromLTRB(16, 16, 16, 24 + MediaQuery.paddingOf(context).bottom);
