import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../theme.dart';
import '../../widgets/common.dart';

/// Join a tutor's class with the code they gave you, so the tutor can follow your progress.
class ClassScreen extends StatefulWidget {
  const ClassScreen({super.key});

  @override
  State<ClassScreen> createState() => _ClassScreenState();
}

class _ClassScreenState extends State<ClassScreen> {
  final _code = TextEditingController();
  List<Map<String, dynamic>> _centres = [];
  bool _loading = true;
  bool _busy = false;

  late final ApiClient _api = context.read<ApiClient>();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get('/api/me/centres');
      setState(() => _centres = (data['data'] as List).map((e) => Map<String, dynamic>.from(e as Map)).toList());
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _join() async {
    if (_code.text.trim().isEmpty) return;
    setState(() => _busy = true);
    try {
      final data = await _api.post('/api/centres/join', data: {'code': _code.text.trim()});
      _code.clear();
      if (mounted) showSnack(context, data['message'] as String? ?? 'Joined.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _leave(Map<String, dynamic> centre) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text('Leave ${centre['name']}?'),
        content: const Text('Your tutor will no longer see your scores.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Leave')),
        ],
      ),
    );
    if (ok != true) return;
    try {
      await _api.delete('/api/centres/${centre['id']}/leave');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Your class')),
      body: _loading
          ? const LoadingView()
          : ListView(padding: pagePadding(context), children: [
              const Text('Studying with a tutor or prep centre? Join their class with the code they gave you.',
                  style: TextStyle(color: AppColors.muted, height: 1.4)),
              const SizedBox(height: 16),
              for (final c in _centres)
                Padding(
                  padding: const EdgeInsets.only(bottom: 10),
                  child: AppCard(
                    child: Row(children: [
                      const Icon(Icons.school_rounded, color: AppColors.brand),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${c['name']}', style: const TextStyle(fontWeight: FontWeight.w700)),
                          if (c['tutor'] != null) Text('Tutor: ${c['tutor']}', style: const TextStyle(color: AppColors.muted, fontSize: 13)),
                        ]),
                      ),
                      TextButton(onPressed: () => _leave(c), child: const Text('Leave')),
                    ]),
                  ),
                ),
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  TextField(
                    controller: _code,
                    textCapitalization: TextCapitalization.characters,
                    autocorrect: false,
                    decoration: const InputDecoration(labelText: 'Class code', hintText: 'e.g. TRACT-7K2M'),
                    onSubmitted: (_) => _join(),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Your tutor will see your name, email, plan, test date, target band and practice scores. You can leave at any time.',
                    style: TextStyle(color: AppColors.muted, fontSize: 12),
                  ),
                  const SizedBox(height: 12),
                  FilledButton(onPressed: _busy ? null : _join, child: Text(_busy ? 'Joining…' : 'Join class')),
                ]),
              ),
            ]),
    );
  }
}
