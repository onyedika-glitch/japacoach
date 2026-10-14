import 'package:flutter/material.dart';

import '../api/api_client.dart';
import '../theme.dart';

/// Shown when the free plan's limit is reached. The app does not sell plans (Google Play billing rules).
Future<void> showLimitDialog(BuildContext context, ApiException error) {
  final message = error.message.replaceAll(RegExp(r'\s*Upgrade your plan to keep practising\.?'), '');
  return showDialog<void>(
    context: context,
    builder: (context) => AlertDialog(
      icon: const Icon(Icons.hourglass_bottom_rounded, color: AppColors.accent, size: 36),
      title: const Text("You've reached your limit"),
      content: Text('$message Your free practice allowance refreshes every 30 days.', textAlign: TextAlign.center),
      actions: [FilledButton(onPressed: () => Navigator.of(context).pop(), child: const Text('OK'))],
    ),
  );
}
