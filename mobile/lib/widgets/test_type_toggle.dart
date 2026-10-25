import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../state/auth.dart';
import 'common.dart';

/// Switches the user's IELTS type (Academic or General Training), which decides the
/// Writing Task 1 and Reading papers they get. Calls [onChanged] after saving.
class TestTypeToggle extends StatefulWidget {
  const TestTypeToggle({super.key, this.onChanged});
  final VoidCallback? onChanged;

  @override
  State<TestTypeToggle> createState() => _TestTypeToggleState();
}

class _TestTypeToggleState extends State<TestTypeToggle> {
  bool _busy = false;

  Future<void> _choose(String type) async {
    final auth = context.read<AuthState>();
    if (_busy || auth.user?.testType == type) return;
    setState(() => _busy = true);
    try {
      await auth.updateProfile({'test_type': type});
      widget.onChanged?.call();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final current = context.select<AuthState, String>((a) => a.user?.testType ?? 'academic');
    return SizedBox(
      width: double.infinity,
      child: SegmentedButton<String>(
        segments: const [
          ButtonSegment(value: 'academic', label: Text('Academic')),
          ButtonSegment(value: 'general', label: Text('General Training')),
        ],
        selected: {current},
        showSelectedIcon: false,
        onSelectionChanged: _busy ? null : (v) => _choose(v.first),
      ),
    );
  }
}
