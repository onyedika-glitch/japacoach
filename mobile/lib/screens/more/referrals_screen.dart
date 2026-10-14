import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../api/api_client.dart';
import '../../theme.dart';
import '../../widgets/async_loader.dart';
import '../../widgets/common.dart';

class ReferralsScreen extends StatelessWidget {
  const ReferralsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final api = context.read<ApiClient>();
    return Scaffold(
      appBar: AppBar(title: const Text('Refer friends')),
      body: AsyncLoader<Map<String, dynamic>>(
        load: () => api.get('/api/referrals'),
        builder: (context, d, _) {
          final code = d['code']?.toString() ?? '';
          final link = d['link']?.toString() ?? '';
          final message = 'I use Japa Coach to practise for IELTS with an AI examiner. Sign up with my code $code and get a free extra speaking mock: $link';
          return ListView(padding: pagePadding(context), children: [
            AppCard(
              color: AppColors.brand50,
              padding: const EdgeInsets.all(20),
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Icon(Icons.card_giftcard_rounded, size: 40, color: AppColors.brand),
                const SizedBox(height: 10),
                Text('Give a mock, get a mock', style: grotesk(size: 22)),
                const SizedBox(height: 6),
                const Text(
                  'Friends who join with your code get a bonus full speaking mock. You earn 2 more when your friend upgrades their plan.',
                  style: TextStyle(color: AppColors.muted, height: 1.45),
                ),
              ]),
            ),
            const SizedBox(height: 16),
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                const Text('Your code', style: TextStyle(color: AppColors.muted)),
                Row(children: [
                  Expanded(child: SelectableText(code, style: grotesk(size: 28))),
                  IconButton(
                    tooltip: 'Copy link',
                    icon: const Icon(Icons.copy_rounded),
                    onPressed: () {
                      Clipboard.setData(ClipboardData(text: link));
                      showSnack(context, 'Link copied');
                    },
                  ),
                ]),
                const SizedBox(height: 10),
                FilledButton.icon(
                  onPressed: () => SharePlus.instance.share(ShareParams(text: message)),
                  icon: const Icon(Icons.share_rounded),
                  label: const Text('Share with friends'),
                ),
              ]),
            ),
            const SizedBox(height: 16),
            Row(children: [
              Expanded(child: _Count('Friends joined', '${d['invited_count'] ?? 0}')),
              const SizedBox(width: 10),
              Expanded(child: _Count('Bonus mocks', '${d['bonus_speaking_mocks'] ?? 0}')),
            ]),
          ]);
        },
      ),
    );
  }
}

class _Count extends StatelessWidget {
  const _Count(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => AppCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(color: AppColors.muted, fontSize: 13)),
          Text(value, style: grotesk(size: 26)),
        ]),
      );
}
