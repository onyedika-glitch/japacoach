import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../api/api_client.dart';
import '../theme.dart';

/// Shown when a plan limit is reached. Offers the in-app plans (Google Play) where they can be bought.
Future<void> showLimitDialog(BuildContext context, ApiException error, {bool canBuy = false}) {
  final message = error.message.replaceAll(RegExp(r'\s*Upgrade your plan to keep practising\.?'), '');
  return showDialog<void>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: const Icon(Icons.hourglass_bottom_rounded, color: AppColors.accent, size: 36),
      title: const Text("You've reached your limit"),
      content: Text(
        canBuy ? '$message Upgrade your plan to keep practising.' : '$message Your free practice allowance refreshes every 30 days.',
        textAlign: TextAlign.center,
      ),
      actions: [
        if (canBuy) ...[
          TextButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('Not now')),
          FilledButton(
            onPressed: () {
              Navigator.of(dialogContext).pop();
              context.push('/plans');
            },
            child: const Text('See plans'),
          ),
        ] else
          FilledButton(onPressed: () => Navigator.of(dialogContext).pop(), child: const Text('OK')),
      ],
    ),
  );
}
