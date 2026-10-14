import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../config.dart';
import '../../state/auth.dart';
import '../../theme.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthState>();
    final user = auth.user;
    void web(String path) => launchUrl(Uri.parse('${AppConfig.websiteUrl}$path'), mode: LaunchMode.externalApplication);

    return Scaffold(
      appBar: AppBar(title: const Text('More')),
      body: ListView(padding: pagePadding(context), children: [
        if (user != null)
          AppCard(
            child: Row(children: [
              CircleAvatar(
                radius: 26,
                backgroundColor: AppColors.brand100,
                foregroundImage: user.avatarUrl != null ? NetworkImage(user.avatarUrl!) : null,
                child: Text(user.name.isEmpty ? '?' : user.name[0].toUpperCase(), style: grotesk(size: 20, color: AppColors.brandDark)),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(user.name, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
                  Text(user.email, style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                  const SizedBox(height: 4),
                  Pill(user.isPremium && user.planExpiresAt != null
                      ? '${planLabel(user.plan)} until ${formatDate(user.planExpiresAt)}'
                      : 'Free plan'),
                ]),
              ),
            ]),
          ),
        const SizedBox(height: 16),
        _Group(children: [
          _Item(icon: Icons.assignment_rounded, label: 'Full mock exam', onTap: () => context.push('/mock')),
          _Item(icon: Icons.calendar_month_rounded, label: 'Study plan', onTap: () => context.push('/study-plan')),
          _Item(icon: Icons.card_giftcard_rounded, label: 'Refer friends', onTap: () => context.push('/referrals')),
        ]),
        const SizedBox(height: 16),
        _Group(children: [
          _Item(icon: Icons.settings_rounded, label: 'Settings and review', onTap: () => context.push('/settings')),
          _Item(icon: Icons.mail_outline_rounded, label: 'Contact support', onTap: () => launchUrl(Uri.parse('mailto:${AppConfig.supportEmail}'))),
          _Item(icon: Icons.description_outlined, label: 'Terms of use', onTap: () => web('/terms')),
          _Item(icon: Icons.privacy_tip_outlined, label: 'Privacy policy', onTap: () => web('/privacy')),
        ]),
        const SizedBox(height: 16),
        _Group(children: [
          _Item(
            icon: Icons.logout_rounded,
            label: 'Log out',
            color: AppColors.bad,
            onTap: () async {
              final ok = await showDialog<bool>(
                context: context,
                builder: (context) => AlertDialog(
                  title: const Text('Log out?'),
                  actions: [
                    TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
                    TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Log out')),
                  ],
                ),
              );
              if (ok == true) await auth.logout();
            },
          ),
        ]),
        const SizedBox(height: 20),
        const Text(
          'Japa Coach is not affiliated with IELTS, the British Council, IDP or Cambridge. Band scores are estimates.',
          textAlign: TextAlign.center,
          style: TextStyle(color: AppColors.muted, fontSize: 12),
        ),
      ]),
    );
  }
}

class _Group extends StatelessWidget {
  const _Group({required this.children});
  final List<Widget> children;

  @override
  Widget build(BuildContext context) => AppCard(
        padding: EdgeInsets.zero,
        child: Column(children: [
          for (final (i, c) in children.indexed) ...[if (i > 0) const Divider(indent: 56), c],
        ]),
      );
}

class _Item extends StatelessWidget {
  const _Item({required this.icon, required this.label, required this.onTap, this.color});
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final Color? color;

  @override
  Widget build(BuildContext context) => ListTile(
        leading: Icon(icon, color: color ?? AppColors.brandDark),
        title: Text(label, style: TextStyle(fontWeight: FontWeight.w600, color: color)),
        trailing: color == null ? const Icon(Icons.chevron_right_rounded, color: AppColors.muted) : null,
        onTap: onTap,
      );
}
