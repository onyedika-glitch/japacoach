import 'dart:async';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:provider/provider.dart';

import '../../api/api_client.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/audio_button.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';
import 'question_input.dart';

class PracticeTestScreen extends StatefulWidget {
  const PracticeTestScreen({super.key, required this.attemptId, this.mockId});
  final int attemptId;
  final int? mockId;

  @override
  State<PracticeTestScreen> createState() => _PracticeTestScreenState();
}

class _PracticeTestScreenState extends State<PracticeTestScreen> {
  late final ApiClient _api = context.read<ApiClient>();
  PracticeAttempt? _attempt;
  String? _error;
  final Map<String, String> _answers = {};
  int _section = 0;
  bool _showPassage = true;
  bool _dirty = false;
  bool _saving = false;
  bool _submitting = false;
  Timer? _ticker;
  Timer? _autosave;
  int _remaining = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _autosave?.cancel();
    if (_dirty && !(_attempt?.isSubmitted ?? true)) _save();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get('/api/practice/attempts/${widget.attemptId}');
      final a = PracticeAttempt.fromJson(Map<String, dynamic>.from(data['attempt'] as Map));
      if (!mounted) return;
      if (a.isSubmitted) {
        context.pushReplacement('/practice/result/${a.id}');
        return;
      }
      _answers.addAll(a.answers);
      final deadline = a.startedAt.add(Duration(minutes: a.durationMinutes));
      setState(() {
        _attempt = a;
        _remaining = deadline.difference(DateTime.now()).inSeconds;
      });
      _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
        setState(() => _remaining = deadline.difference(DateTime.now()).inSeconds);
        if (_remaining <= 0) {
          _ticker?.cancel();
          _submit(timeUp: true);
        }
      });
      _autosave = Timer.periodic(const Duration(seconds: 30), (_) => _save());
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    }
  }

  Future<void> _save() async {
    if (!_dirty || _saving) return;
    _saving = true;
    _dirty = false;
    try {
      await _api.patch('/api/practice/attempts/${widget.attemptId}', data: {'answers': Map<String, String>.from(_answers)});
    } catch (_) {
      _dirty = true;
    } finally {
      _saving = false;
      if (mounted) setState(() {});
    }
  }

  Future<void> _submit({bool timeUp = false}) async {
    final a = _attempt!;
    final total = a.sections.fold<int>(0, (n, s) => n + s.questions.length);
    final answered = _answers.values.where((v) => v.trim().isNotEmpty).length;
    if (!timeUp) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Submit your answers?'),
          content: Text(answered < total ? 'You have answered $answered of $total questions. Unanswered questions are marked wrong.' : 'You have answered every question.'),
          actions: [
            TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Keep going')),
            TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Submit')),
          ],
        ),
      );
      if (ok != true) return;
    }
    setState(() => _submitting = true);
    try {
      await _api.post('/api/practice/attempts/${widget.attemptId}/submit', data: {'answers': Map<String, String>.from(_answers)});
      _dirty = false;
      _ticker?.cancel();
      _autosave?.cancel();
      if (!mounted) return;
      context.pushReplacement(widget.mockId != null ? '/mock/${widget.mockId}' : '/practice/result/${widget.attemptId}');
    } on ApiException catch (e) {
      if (mounted) showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  void _goTo(int section) {
    _save();
    setState(() {
      _section = section;
      _showPassage = true;
    });
  }

  /// General Training texts are titled "Section 1 · Text A: ..."; show that, otherwise "Passage 2" / "Part 3".
  static String _tabLabel(String title, int index, bool isReading) =>
      RegExp(r'^Section \d+(?: · Text [A-Z])?').firstMatch(title)?.group(0) ?? '${isReading ? 'Passage' : 'Part'} ${index + 1}';

  @override
  Widget build(BuildContext context) {
    final a = _attempt;
    if (a == null) {
      return Scaffold(appBar: AppBar(), body: _error != null ? ErrorView(message: _error!, onRetry: _load) : const LoadingView(label: 'Loading test…'));
    }
    final s = a.sections[_section];
    final isReading = a.module == 'reading';
    final hasPassage = isReading && (s.body?.isNotEmpty ?? false);
    final low = _remaining < 300;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) async {
        if (didPop) return;
        await _save();
        if (context.mounted) context.pop();
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(a.testTitle, maxLines: 1, overflow: TextOverflow.ellipsis),
          actions: [
            Padding(
              padding: const EdgeInsets.only(right: 14),
              child: Center(
                child: Row(children: [
                  Icon(Icons.timer_outlined, size: 18, color: low ? AppColors.bad : AppColors.muted),
                  const SizedBox(width: 4),
                  Text(clock(_remaining.clamp(0, 99999)), style: TextStyle(fontWeight: FontWeight.w700, color: low ? AppColors.bad : AppColors.ink)),
                ]),
              ),
            ),
          ],
          bottom: PreferredSize(
            preferredSize: const Size.fromHeight(48),
            child: SizedBox(
              height: 48,
              child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8), children: [
                for (final (i, sec) in a.sections.indexed)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text('${_tabLabel(sec.title, i, isReading)} · ${sec.questions.where((q) => (_answers['${q.id}'] ?? '').isNotEmpty).length}/${sec.questions.length}'),
                      selected: i == _section,
                      showCheckmark: false,
                      selectedColor: AppColors.brand100,
                      onSelected: (_) => _goTo(i),
                    ),
                  ),
              ]),
            ),
          ),
        ),
        body: Column(children: [
          if (!isReading && s.audioUrl != null)
            Container(
              color: Colors.white,
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 8),
              child: AudioPlayerBar(key: ValueKey(s.id), url: s.audioUrl!, label: s.title),
            ),
          if (hasPassage)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
              child: SegmentedButton<bool>(
                segments: const [ButtonSegment(value: true, label: Text('Passage')), ButtonSegment(value: false, label: Text('Questions'))],
                selected: {_showPassage},
                onSelectionChanged: (v) => setState(() => _showPassage = v.first),
              ),
            ),
          Expanded(
            child: ListView(key: ValueKey('$_section-$_showPassage'), padding: const EdgeInsets.all(16), children: [
              if (hasPassage && _showPassage) ...[
                Text(s.title, style: grotesk(size: 20)),
                const SizedBox(height: 10),
                for (final para in s.body!.split(RegExp(r'\n\s*\n')))
                  Padding(padding: const EdgeInsets.only(bottom: 12), child: Text(para.trim(), style: const TextStyle(fontSize: 16, height: 1.6))),
                const SizedBox(height: 8),
                OutlinedButton(onPressed: () => setState(() => _showPassage = false), child: const Text('Go to the questions')),
              ] else ...[
                if (!hasPassage) ...[Text(s.title, style: grotesk(size: 20)), const SizedBox(height: 6)],
                if (s.instructions.isNotEmpty) ...[Notice(s.instructions), const SizedBox(height: 16)],
                for (final q in s.questions)
                  QuestionInput(
                    key: ValueKey(q.id),
                    question: q,
                    value: _answers['${q.id}'],
                    onChanged: (v) => setState(() {
                      _answers['${q.id}'] = v;
                      _dirty = true;
                    }),
                  ),
                const SizedBox(height: 8),
                if (_section < a.sections.length - 1)
                  FilledButton(onPressed: () => _goTo(_section + 1), child: Text('Next ${isReading ? 'passage' : 'part'}'))
                else
                  FilledButton(onPressed: _submitting ? null : () => _submit(), child: Text(_submitting ? 'Submitting…' : 'Submit answers')),
              ],
              SizedBox(height: MediaQuery.paddingOf(context).bottom),
            ]),
          ),
        ]),
      ),
    );
  }
}
