import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../state/auth.dart';
import '../../state/billing.dart';
import '../../theme.dart';
import '../../widgets/async_loader.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';

class PlansScreen extends StatefulWidget {
  const PlansScreen({super.key});

  @override
  State<PlansScreen> createState() => _PlansScreenState();
}

class _PlansScreenState extends State<PlansScreen> {
  @override
  void initState() {
    super.initState();
    final billing = context.read<BillingState>();
    if (billing.products.isEmpty) billing.loadProducts();
  }

  @override
  Widget build(BuildContext context) {
    final billing = context.watch<BillingState>();
    final user = context.watch<AuthState>().user;
    final api = context.read<ApiClient>();

    return Scaffold(
      appBar: AppBar(title: const Text('Plans')),
      body: AsyncLoader<List<Map<String, dynamic>>>(
        load: () async => ((await api.get('/api/billing/plans'))['plans'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList(),
        builder: (context, plans, _) => ListView(padding: pagePadding(context), children: [
          if (user != null)
            AppCard(
              color: AppColors.ink,
              child: Row(children: [
                const Icon(Icons.workspace_premium_rounded, color: AppColors.accent, size: 32),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(user.isPremium ? '${planLabel(user.plan)} plan' : 'Free plan', style: grotesk(size: 18, color: Colors.white)),
                    Text(
                      user.isPremium && user.planExpiresAt != null ? 'Active until ${formatDate(user.planExpiresAt)}' : 'Upgrade for full mocks and more practice',
                      style: const TextStyle(color: Colors.white70),
                    ),
                  ]),
                ),
              ]),
            ),
          const SizedBox(height: 16),
          if (!billing.supported)
            const Notice('Plans can be bought in the Android app.')
          else if (billing.loading)
            const Padding(padding: EdgeInsets.all(32), child: LoadingView())
          else if (!billing.available || billing.products.isEmpty)
            Notice(
              billing.available ? 'Plans are not available right now. Please try again later.' : 'Google Play is not available on this device. Sign in to the Play Store and try again.',
              tone: 'warning',
            )
          else
            for (final productId in const ['pass_weekly', 'pass_monthly', 'pass_sprint'])
              if (billing.products[productId] case final product?)
                if (plans.where((p) => p['id'] == BillingState.planForProduct[productId]).firstOrNull case final plan?)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _PlanCard(
                      name: plan['name'] as String,
                      days: (plan['days'] as num).toInt(),
                      price: product.price,
                      features: (plan['features'] as List).map((e) => e.toString()).toList(),
                      highlight: plan['highlight'] == true,
                      busy: billing.busyProductId == productId,
                      onBuy: billing.busyProductId == null ? () => billing.buy(productId) : null,
                    ),
                  ),
          const SizedBox(height: 4),
          const Text(
            'One-time payment through Google Play. No automatic renewal. If you already have a plan, the new days are added on top.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.muted, fontSize: 13),
          ),
        ]),
      ),
    );
  }
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.name, required this.days, required this.price, required this.features, required this.highlight, required this.busy, this.onBuy});
  final String name;
  final int days;
  final String price;
  final List<String> features;
  final bool highlight;
  final bool busy;
  final VoidCallback? onBuy;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: highlight ? AppColors.brand : AppColors.border, width: highlight ? 2 : 1),
      ),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Row(children: [
          Expanded(child: Text(name, style: grotesk(size: 18))),
          if (highlight) const Pill('Most popular', color: Colors.white, background: AppColors.brand),
        ]),
        const SizedBox(height: 6),
        Text.rich(TextSpan(children: [
          TextSpan(text: price, style: grotesk(size: 30)),
          TextSpan(text: '  for $days days', style: const TextStyle(color: AppColors.muted)),
        ])),
        const SizedBox(height: 10),
        for (final f in features)
          Padding(
            padding: const EdgeInsets.only(bottom: 6),
            child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Icon(Icons.check_rounded, color: AppColors.good, size: 20),
              const SizedBox(width: 8),
              Expanded(child: Text(f)),
            ]),
          ),
        const SizedBox(height: 8),
        SizedBox(
          width: double.infinity,
          child: highlight
              ? FilledButton(onPressed: onBuy, child: Text(busy ? 'Opening Google Play…' : 'Get $name'))
              : OutlinedButton(onPressed: onBuy, child: Text(busy ? 'Opening Google Play…' : 'Get $name')),
        ),
      ]),
    );
  }
}
