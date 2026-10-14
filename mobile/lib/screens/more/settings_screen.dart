import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../state/auth.dart';
import '../../theme.dart';
import '../../widgets/common.dart';
import '../../widgets/review_card.dart';

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  late final AuthState _auth = context.read<AuthState>();
  late final _name = TextEditingController(text: _auth.user?.name ?? '');
  late String _testType = _auth.user?.testType ?? 'academic';
  late double? _target = _auth.user?.targetBand;
  late DateTime? _testDate = _auth.user?.testDate;
  late bool _reminders = _auth.user?.emailReminders ?? true;
  bool _saving = false;

  static final _bands = [for (var b = 4.0; b <= 9.0; b += 0.5) b];

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await _auth.updateProfile({
        'name': _name.text.trim(),
        'test_type': _testType,
        'target_band': _target,
        'test_date': _testDate == null ? null : DateFormat('yyyy-MM-dd').format(_testDate!),
        'email_reminders': _reminders,
      });
      if (mounted) showSnack(context, 'Saved');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _deleteAccount() async {
    final confirm = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Delete your account?'),
          content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Text('This permanently deletes your account, test history, recordings and reports. It cannot be undone. '
                'Days left on a paid plan are lost.'),
            const SizedBox(height: 12),
            TextField(
              controller: confirm,
              onChanged: (_) => setDialogState(() {}),
              decoration: const InputDecoration(labelText: 'Type DELETE to confirm'),
            ),
          ]),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
            TextButton(
              onPressed: confirm.text.trim() == 'DELETE' ? () => Navigator.pop(context, true) : null,
              style: TextButton.styleFrom(foregroundColor: AppColors.bad),
              child: const Text('Delete forever'),
            ),
          ],
        ),
      ),
    );
    if (ok != true) return;
    try {
      await _auth.deleteAccount();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    }
  }

  @override
  void dispose() {
    _name.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthState>().user;
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(padding: pagePadding(context), children: [
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Your IELTS profile', style: grotesk(size: 17)),
            const SizedBox(height: 12),
            SegmentedButton<String>(
              segments: const [ButtonSegment(value: 'academic', label: Text('Academic')), ButtonSegment(value: 'general', label: Text('General'))],
              selected: {_testType},
              onSelectionChanged: (v) => setState(() => _testType = v.first),
            ),
            const SizedBox(height: 6),
            const Text('Changes which Writing Task 1 and Reading papers you see.', style: TextStyle(color: AppColors.muted, fontSize: 12)),
            const SizedBox(height: 14),
            DropdownButtonFormField<double?>(
              initialValue: _target,
              decoration: const InputDecoration(labelText: 'Target band'),
              items: [
                const DropdownMenuItem(value: null, child: Text('Not set')),
                for (final b in _bands) DropdownMenuItem(value: b, child: Text(b.toStringAsFixed(1))),
              ],
              onChanged: (v) => setState(() => _target = v),
            ),
            const SizedBox(height: 14),
            InkWell(
              borderRadius: BorderRadius.circular(12),
              onTap: () async {
                final now = DateTime.now();
                final picked = await showDatePicker(
                  context: context,
                  initialDate: _testDate != null && _testDate!.isAfter(now) ? _testDate! : now.add(const Duration(days: 30)),
                  firstDate: now,
                  lastDate: now.add(const Duration(days: 730)),
                );
                if (picked != null) setState(() => _testDate = picked);
              },
              child: InputDecorator(
                decoration: InputDecoration(
                  labelText: 'Test date',
                  suffixIcon: _testDate == null ? const Icon(Icons.event_rounded) : IconButton(icon: const Icon(Icons.clear), onPressed: () => setState(() => _testDate = null)),
                ),
                child: Text(_testDate == null ? 'Not set' : DateFormat('d MMMM yyyy').format(_testDate!)),
              ),
            ),
            const SizedBox(height: 20),
            Text('Account', style: grotesk(size: 17)),
            const SizedBox(height: 12),
            TextField(controller: _name, textCapitalization: TextCapitalization.words, decoration: const InputDecoration(labelText: 'Name')),
            const SizedBox(height: 12),
            InputDecorator(decoration: const InputDecoration(labelText: 'Email', enabled: false), child: Text(user?.email ?? '')),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _reminders,
              onChanged: (v) => setState(() => _reminders = v),
              title: const Text('Email reminders', style: TextStyle(fontWeight: FontWeight.w600)),
              subtitle: const Text('Study nudges, "your report is ready" and plan reminders.'),
            ),
            const SizedBox(height: 8),
            SizedBox(width: double.infinity, child: FilledButton(onPressed: _saving ? null : _save, child: Text(_saving ? 'Saving…' : 'Save changes'))),
          ]),
        ),
        const SizedBox(height: 16),
        const ReviewCard(standalone: true),
        const SizedBox(height: 16),
        AppCard(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('Delete account', style: grotesk(size: 17)),
            const SizedBox(height: 6),
            const Text('Permanently remove your account and all your data.', style: TextStyle(color: AppColors.muted)),
            const SizedBox(height: 10),
            OutlinedButton(
              onPressed: _deleteAccount,
              style: OutlinedButton.styleFrom(foregroundColor: AppColors.bad, side: const BorderSide(color: AppColors.bad)),
              child: const Text('Delete my account'),
            ),
          ]),
        ),
      ]),
    );
  }
}
