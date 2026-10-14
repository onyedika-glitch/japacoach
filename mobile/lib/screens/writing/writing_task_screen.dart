import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';
import 'task_visual.dart';

class WritingTaskScreen extends StatefulWidget {
  const WritingTaskScreen({super.key, required this.taskId, this.mockId});
  final int taskId;
  final int? mockId;

  @override
  State<WritingTaskScreen> createState() => _WritingTaskScreenState();
}

class _WritingTaskScreenState extends State<WritingTaskScreen> {
  final _essay = TextEditingController();
  final _started = DateTime.now();
  WritingTask? _task;
  String? _error;
  bool _submitting = false;
  bool _showTask = true;
  Timer? _ticker;
  int _elapsed = 0;

  @override
  void initState() {
    super.initState();
    _load();
    _essay.addListener(() => setState(() {}));
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) => setState(() => _elapsed = DateTime.now().difference(_started).inSeconds));
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _essay.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await context.read<ApiClient>().get('/api/writing/tasks/${widget.taskId}');
      setState(() => _task = WritingTask.fromJson(Map<String, dynamic>.from(data['task'] as Map)));
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    }
  }

  Future<void> _submit() async {
    final task = _task!;
    final words = wordCount(_essay.text);
    if (words < 20) {
      showSnack(context, 'Write a bit more before submitting.');
      return;
    }
    if (words < task.minWords) {
      final go = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Under the word count'),
          content: Text('You have $words words. Task ${task.taskNumber} needs at least ${task.minWords}; short answers lose marks. Submit anyway?'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep writing')),
            TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Submit')),
          ],
        ),
      );
      if (go != true) return;
    }
    if (!mounted) return;
    setState(() => _submitting = true);
    try {
      final data = await context.read<ApiClient>().post('/api/writing/attempts', data: {
        'writing_task_id': task.id,
        'essay': _essay.text,
        'time_spent_seconds': _elapsed,
        'mock_exam_id': ?widget.mockId,
      });
      final id = (data['attempt'] as Map)['id'];
      if (!mounted) return;
      _essay.clear();
      context.pushReplacement(widget.mockId != null ? '/mock/${widget.mockId}' : '/writing/report/$id');
    } on ApiException catch (e) {
      if (mounted && !e.isLimitReached) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final task = _task;
    final words = wordCount(_essay.text);
    final over = task != null && _elapsed > task.recommendedMinutes * 60;
    return PopScope(
      canPop: _essay.text.trim().isEmpty,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        final leave = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: const Text('Discard your essay?'),
            content: const Text('Your writing is not saved until you submit it.'),
            actions: [
              TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep writing')),
              TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Discard')),
            ],
          ),
        );
        if (leave == true && context.mounted) {
          _essay.clear();
          context.pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(task == null ? 'Writing' : 'Task ${task.taskNumber}'),
          actions: [
            if (task != null)
              Padding(
                padding: const EdgeInsets.only(right: 16),
                child: Center(
                  child: Row(children: [
                    Icon(Icons.timer_outlined, size: 18, color: over ? AppColors.bad : AppColors.muted),
                    const SizedBox(width: 4),
                    Text('${clock(_elapsed)} / ${task.recommendedMinutes}:00', style: TextStyle(fontWeight: FontWeight.w600, color: over ? AppColors.bad : AppColors.ink)),
                  ]),
                ),
              ),
          ],
        ),
        body: task == null
            ? (_error != null ? ErrorView(message: _error!, onRetry: _load) : const LoadingView())
            : Column(children: [
                Expanded(
                  child: ListView(padding: const EdgeInsets.all(16), children: [
                    AppCard(
                      onTap: () => setState(() => _showTask = !_showTask),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          Expanded(child: Text(task.title, style: grotesk(size: 17))),
                          Icon(_showTask ? Icons.expand_less_rounded : Icons.expand_more_rounded, color: AppColors.muted),
                        ]),
                        if (_showTask) ...[
                          const SizedBox(height: 8),
                          Text(task.prompt, style: const TextStyle(height: 1.5)),
                          if (task.visual != null) ...[const SizedBox(height: 12), TaskVisual(task.visual!)],
                        ],
                      ]),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _essay,
                      maxLines: null,
                      minLines: 14,
                      keyboardType: TextInputType.multiline,
                      textCapitalization: TextCapitalization.sentences,
                      style: const TextStyle(fontSize: 16, height: 1.5),
                      decoration: const InputDecoration(hintText: 'Start writing your answer here…'),
                    ),
                  ]),
                ),
                Container(
                  padding: EdgeInsets.fromLTRB(16, 10, 16, 10 + MediaQuery.paddingOf(context).bottom),
                  decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: AppColors.border))),
                  child: Row(children: [
                    Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                      Text('$words words', style: grotesk(size: 17, color: words >= task.minWords ? AppColors.good : AppColors.ink)),
                      Text('Minimum ${task.minWords}', style: const TextStyle(color: AppColors.muted, fontSize: 12)),
                    ]),
                    const Spacer(),
                    FilledButton(
                      onPressed: _submitting ? null : _submit,
                      child: Text(_submitting ? 'Submitting…' : 'Submit for marking'),
                    ),
                  ]),
                ),
              ]),
      ),
    );
  }
}
