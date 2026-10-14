import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:just_audio/just_audio.dart';
import 'package:path_provider/path_provider.dart';
import 'package:provider/provider.dart';
import 'package:record/record.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

import '../../api/api_client.dart';
import '../../config.dart';
import '../../models/models.dart';
import '../../theme.dart';
import '../../widgets/common.dart';
import '../../widgets/format.dart';

enum _Stage { loading, ready, running, finishing }

enum _Phase { intro, listening, prep, recording }

enum _UploadStatus { uploading, done, error }

class _Upload {
  _Upload(this.status, {this.path, this.seconds, this.error});
  _UploadStatus status;
  final String? path;
  final double? seconds;
  String? error;
}

class SpeakingTestScreen extends StatefulWidget {
  const SpeakingTestScreen({super.key, required this.attemptId, this.mockId});
  final int attemptId;
  final int? mockId;

  @override
  State<SpeakingTestScreen> createState() => _SpeakingTestScreenState();
}

class _SpeakingTestScreenState extends State<SpeakingTestScreen> {
  static const _prepSeconds = 60;
  static const _partInfo = {
    1: ('Part 1: Introduction and interview', 'Short questions about familiar topics. Aim for 2 to 4 sentences per answer.'),
    2: ('Part 2: Individual long turn', "You'll get a cue card and 1 minute to prepare. Then speak for 1 to 2 minutes."),
    3: ('Part 3: Two-way discussion', 'Deeper questions linked to Part 2. Give opinions, reasons and examples.'),
  };

  final _recorder = AudioRecorder();
  final _player = AudioPlayer();
  final _uploads = <int, _Upload>{};
  late final ApiClient _api = context.read<ApiClient>();

  _Stage _stage = _Stage.loading;
  _Phase _phase = _Phase.intro;
  String? _error;
  List<SpeakingQuestion> _questions = [];
  int _index = 0;
  int _resumeIndex = 0;
  int _seconds = 0;
  Timer? _ticker;
  DateTime? _recordStarted;
  bool _submitting = false;
  StreamSubscription<PlayerState>? _playerSub;

  SpeakingQuestion get _current => _questions[_index];
  int get _maxSeconds => _current.part == 2 ? 120 : 60;
  bool get _anyUploading => _uploads.values.any((u) => u.status == _UploadStatus.uploading);
  List<MapEntry<int, _Upload>> get _failed => _uploads.entries.where((e) => e.value.status == _UploadStatus.error).toList();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _ticker?.cancel();
    _playerSub?.cancel();
    _player.dispose();
    _recorder.dispose();
    WakelockPlus.disable();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final data = await _api.get('/api/speaking/attempts/${widget.attemptId}');
      final attempt = SpeakingAttempt.fromJson(Map<String, dynamic>.from(data['attempt'] as Map));
      if (!mounted) return;
      if (attempt.status != 'in_progress') {
        context.pushReplacement('/speaking/report/${attempt.id}');
        return;
      }
      _questions = attempt.questions;
      for (final q in _questions) {
        if (q.answer != null && q.answer!.status != 'failed') _uploads[q.id] = _Upload(_UploadStatus.done);
      }
      final firstOpen = _questions.indexWhere((q) => !_uploads.containsKey(q.id));
      _resumeIndex = firstOpen == -1 ? _questions.length : firstOpen;
      setState(() => _stage = _Stage.ready);
      if (_resumeIndex >= _questions.length && _questions.isNotEmpty) {
        setState(() => _stage = _Stage.finishing);
        await _submit();
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    }
  }

  Future<void> _begin() async {
    if (!await _recorder.hasPermission()) {
      if (mounted) {
        showSnack(context, 'Japa Coach needs your microphone to hear your answers. Allow it in your phone settings.');
      }
      return;
    }
    await WakelockPlus.enable();
    setState(() {
      _stage = _Stage.running;
      _index = _resumeIndex;
    });
    _enterQuestion();
  }

  bool _firstOfPart(int i) => i == 0 || _questions[i - 1].part != _questions[i].part;

  void _enterQuestion() {
    setState(() => _phase = (_firstOfPart(_index) || _index == _resumeIndex) ? _Phase.intro : _Phase.listening);
    if (_phase == _Phase.listening) _ask();
  }

  Future<void> _ask() async {
    setState(() => _phase = _Phase.listening);
    await _playerSub?.cancel();
    final url = _current.audioUrl;
    if (url == null) return; // The question text is on screen; the user taps "Start answering".
    try {
      await _player.setUrl(AppConfig.absolute(url));
      _playerSub = _player.playerStateStream.listen((s) {
        if (s.processingState == ProcessingState.completed) _afterQuestion();
      });
      unawaited(_player.play());
    } catch (_) {
      // Audio failed to load: the user reads the question instead.
    }
  }

  void _afterQuestion() {
    _playerSub?.cancel();
    _player.stop();
    if (!mounted || _phase != _Phase.listening) return;
    if (_current.part == 2) {
      _startPrep();
    } else {
      _startRecording();
    }
  }

  void _startPrep() {
    setState(() {
      _phase = _Phase.prep;
      _seconds = _prepSeconds;
    });
    _ticker?.cancel();
    _ticker = Timer.periodic(const Duration(seconds: 1), (t) {
      setState(() => _seconds--);
      if (_seconds <= 0) _startRecording();
    });
  }

  Future<void> _startRecording() async {
    _ticker?.cancel();
    await _playerSub?.cancel();
    await _player.stop();
    final dir = await getTemporaryDirectory();
    final path = '${dir.path}/answer-${widget.attemptId}-${_current.id}-${DateTime.now().millisecondsSinceEpoch}.m4a';
    try {
      await _recorder.start(const RecordConfig(encoder: AudioEncoder.aacLc, bitRate: 64000, sampleRate: 16000, numChannels: 1), path: path);
    } catch (_) {
      if (mounted) showSnack(context, 'Could not start the microphone. Close other apps using it and try again.');
      return;
    }
    _recordStarted = DateTime.now();
    setState(() {
      _phase = _Phase.recording;
      _seconds = 0;
    });
    _ticker = Timer.periodic(const Duration(seconds: 1), (t) {
      setState(() => _seconds++);
      if (_seconds >= _maxSeconds) _finishAnswer();
    });
  }

  Future<void> _finishAnswer() async {
    if (_phase != _Phase.recording) return;
    _ticker?.cancel();
    final path = await _recorder.stop();
    final seconds = DateTime.now().difference(_recordStarted!).inMilliseconds / 1000;
    final question = _current;
    if (path != null) unawaited(_upload(question.id, path, seconds));

    if (_index < _questions.length - 1) {
      setState(() => _index++);
      _enterQuestion();
    } else {
      setState(() => _stage = _Stage.finishing);
      await WakelockPlus.disable();
      while (_anyUploading) {
        await Future<void>.delayed(const Duration(milliseconds: 300));
      }
      if (_failed.isEmpty) await _submit();
    }
  }

  Future<void> _upload(int questionId, String path, double seconds) async {
    setState(() => _uploads[questionId] = _Upload(_UploadStatus.uploading, path: path, seconds: seconds));
    try {
      final form = FormData.fromMap({
        'question_id': questionId,
        'duration_seconds': seconds.toStringAsFixed(1),
        'audio': await MultipartFile.fromFile(path, filename: 'answer-$questionId.m4a', contentType: DioMediaType('audio', 'mp4')),
      });
      await _api.post('/api/speaking/attempts/${widget.attemptId}/answers', data: form, timeout: const Duration(minutes: 2));
      if (!mounted) return;
      setState(() => _uploads[questionId] = _Upload(_UploadStatus.done));
      unawaited(File(path).delete().catchError((_) => File(path)));
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _uploads[questionId] = _Upload(_UploadStatus.error, path: path, seconds: seconds, error: e.message));
    }
  }

  Future<void> _retry(int questionId) async {
    final u = _uploads[questionId];
    if (u?.path == null) return;
    await _upload(questionId, u!.path!, u.seconds ?? 0);
    if (_stage == _Stage.finishing && _failed.isEmpty && !_anyUploading) await _submit();
  }

  Future<void> _submit() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await _api.post('/api/speaking/attempts/${widget.attemptId}/submit');
      if (!mounted) return;
      context.pushReplacement(widget.mockId != null ? '/mock/${widget.mockId}' : '/speaking/report/${widget.attemptId}');
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<bool> _confirmLeave() async {
    if (_stage != _Stage.running) return true;
    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Leave the test?'),
        content: const Text('Answers you already finished are saved. You can continue this test later from your history.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Stay')),
          TextButton(onPressed: () => Navigator.pop(context, true), child: const Text('Leave')),
        ],
      ),
    );
    if (leave == true) {
      _ticker?.cancel();
      await _player.stop();
      if (await _recorder.isRecording()) await _recorder.stop();
    }
    return leave ?? false;
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: _stage != _Stage.running,
      onPopInvokedWithResult: (didPop, _) async {
        if (!didPop && await _confirmLeave() && context.mounted) context.pop();
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(_stage == _Stage.running ? 'Question ${_index + 1} of ${_questions.length}' : 'Speaking test'),
          bottom: _stage == _Stage.running
              ? PreferredSize(
                  preferredSize: const Size.fromHeight(4),
                  child: LinearProgressIndicator(value: (_index + 1) / _questions.length, minHeight: 4, color: AppColors.brand, backgroundColor: AppColors.brand100),
                )
              : null,
        ),
        body: SafeArea(child: _body()),
      ),
    );
  }

  Widget _body() {
    if (_error != null && _stage == _Stage.loading) return ErrorView(message: _error!, onRetry: _load);
    return switch (_stage) {
      _Stage.loading => const LoadingView(label: 'Loading your test…'),
      _Stage.ready => _readyView(),
      _Stage.running => _questionView(),
      _Stage.finishing => _finishingView(),
    };
  }

  Widget _readyView() {
    final resuming = _resumeIndex > 0;
    return ListView(padding: const EdgeInsets.all(20), children: [
      const Icon(Icons.headset_mic_rounded, size: 56, color: AppColors.brand),
      const SizedBox(height: 14),
      Text(resuming ? 'Continue your test' : 'Before you start', textAlign: TextAlign.center, style: grotesk(size: 26)),
      const SizedBox(height: 18),
      const BulletCard(
        title: 'Tips',
        icon: Icons.tips_and_updates_outlined,
        color: AppColors.brand,
        items: [
          'Find a quiet place and hold the phone about 20 cm from your mouth.',
          'The examiner asks each question out loud. Recording starts when the question ends.',
          'Speak naturally and keep going. Tap "Done" when you finish an answer.',
          'Part 1 and 3 answers stop after 1 minute, Part 2 after 2 minutes.',
        ],
      ),
      const SizedBox(height: 14),
      Text('${_questions.length} questions${resuming ? ' · ${_questions.length - _resumeIndex} left' : ''}',
          textAlign: TextAlign.center, style: const TextStyle(color: AppColors.muted)),
      const SizedBox(height: 18),
      FilledButton.icon(onPressed: _begin, icon: const Icon(Icons.mic_rounded), label: Text(resuming ? 'Continue test' : 'Start test')),
    ]);
  }

  Widget _questionView() {
    final q = _current;
    final info = _partInfo[q.part]!;
    return Column(children: [
      Expanded(
        child: ListView(padding: const EdgeInsets.all(20), children: [
          Pill('Part ${q.part}'),
          const SizedBox(height: 14),
          if (_phase == _Phase.intro) ...[
            Text(info.$1, style: grotesk(size: 24)),
            const SizedBox(height: 10),
            Text(info.$2, style: const TextStyle(fontSize: 16, color: AppColors.muted, height: 1.45)),
          ] else ...[
            Text(q.prompt, style: grotesk(size: 22, weight: FontWeight.w600)),
            if (q.part == 2 && q.cuePoints.isNotEmpty) ...[
              const SizedBox(height: 14),
              AppCard(
                color: AppColors.brand50,
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  const Text('You should say:', style: TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(height: 6),
                  for (final c in q.cuePoints) Padding(padding: const EdgeInsets.only(bottom: 4), child: Text('•  $c', style: const TextStyle(height: 1.4))),
                ]),
              ),
            ],
            if (_phase == _Phase.prep) ...[
              const SizedBox(height: 14),
              const TextField(
                maxLines: 5,
                decoration: InputDecoration(hintText: 'Notes (only for you, not marked)'),
              ),
            ],
          ],
          const SizedBox(height: 20),
          if (_failed.isNotEmpty) const Notice('An earlier answer failed to upload. We will ask you to retry at the end.', tone: 'warning'),
        ]),
      ),
      _controls(),
    ]);
  }

  Widget _controls() {
    final q = _current;
    final Widget content = switch (_phase) {
      _Phase.intro => FilledButton.icon(onPressed: _ask, icon: const Icon(Icons.volume_up_rounded), label: const Text('Play the question')),
      _Phase.listening => Column(mainAxisSize: MainAxisSize.min, children: [
          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            const Icon(Icons.graphic_eq_rounded, color: AppColors.brand),
            const SizedBox(width: 8),
            Text(q.audioUrl == null ? 'Read the question, then answer' : 'The examiner is speaking…', style: const TextStyle(fontWeight: FontWeight.w600)),
          ]),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () => q.part == 2 ? _startPrep() : _startRecording(),
            child: Text(q.part == 2 ? 'Start preparing' : 'Start answering now'),
          ),
          if (q.audioUrl != null) TextButton(onPressed: _ask, child: const Text('Replay question')),
        ]),
      _Phase.prep => Column(mainAxisSize: MainAxisSize.min, children: [
          Text('Preparation time', style: const TextStyle(color: AppColors.muted)),
          Text(clock(_seconds), style: grotesk(size: 40)),
          const SizedBox(height: 10),
          FilledButton.icon(onPressed: _startRecording, icon: const Icon(Icons.mic_rounded), label: const Text('Start speaking now')),
        ]),
      _Phase.recording => Column(mainAxisSize: MainAxisSize.min, children: [
          Row(mainAxisAlignment: MainAxisAlignment.center, children: [
            const _PulsingDot(),
            const SizedBox(width: 10),
            Text('Recording  ${clock(_seconds)} / ${clock(_maxSeconds)}', style: grotesk(size: 18, weight: FontWeight.w600)),
          ]),
          const SizedBox(height: 8),
          LinearProgressIndicator(value: _seconds / _maxSeconds, color: _seconds > _maxSeconds - 10 ? AppColors.accent : AppColors.brand, backgroundColor: AppColors.brand100),
          const SizedBox(height: 12),
          FilledButton.icon(
            onPressed: _seconds >= 2 ? _finishAnswer : null,
            icon: const Icon(Icons.check_rounded),
            label: Text(_index == _questions.length - 1 ? 'Done, finish test' : 'Done, next question'),
          ),
        ]),
    };
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
      decoration: const BoxDecoration(color: Colors.white, border: Border(top: BorderSide(color: AppColors.border))),
      child: content,
    );
  }

  Widget _finishingView() {
    final failed = _failed;
    final uploading = _anyUploading;
    return ListView(padding: const EdgeInsets.all(24), children: [
      const SizedBox(height: 30),
      Icon(failed.isNotEmpty ? Icons.cloud_off_rounded : Icons.cloud_upload_rounded, size: 56, color: failed.isNotEmpty ? AppColors.accent : AppColors.brand),
      const SizedBox(height: 16),
      Text(
        failed.isNotEmpty ? 'Some answers did not upload' : (uploading || _submitting ? 'Sending your answers…' : 'Almost done'),
        textAlign: TextAlign.center,
        style: grotesk(size: 24),
      ),
      const SizedBox(height: 10),
      Text(
        failed.isNotEmpty ? 'Check your connection and retry. Your recordings are still on this phone.' : 'Keep the app open for a moment.',
        textAlign: TextAlign.center,
        style: const TextStyle(color: AppColors.muted),
      ),
      const SizedBox(height: 20),
      if (uploading || _submitting) const Center(child: CircularProgressIndicator()),
      for (final e in failed)
        Padding(
          padding: const EdgeInsets.only(bottom: 10),
          child: AppCard(
            child: Row(children: [
              Expanded(child: Text('Question ${_questions.indexWhere((q) => q.id == e.key) + 1}: ${e.value.error ?? 'Upload failed'}')),
              TextButton(onPressed: () => _retry(e.key), child: const Text('Retry')),
            ]),
          ),
        ),
      if (_error != null) ...[
        Notice(_error!, tone: 'error'),
        const SizedBox(height: 12),
        FilledButton(onPressed: _submitting ? null : _submit, child: const Text('Try submitting again')),
      ],
    ]);
  }
}

class _PulsingDot extends StatefulWidget {
  const _PulsingDot();

  @override
  State<_PulsingDot> createState() => _PulsingDotState();
}

class _PulsingDotState extends State<_PulsingDot> with SingleTickerProviderStateMixin {
  late final _c = AnimationController(vsync: this, duration: const Duration(milliseconds: 800))..repeat(reverse: true);

  @override
  void dispose() {
    _c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FadeTransition(
        opacity: Tween(begin: 0.3, end: 1.0).animate(_c),
        child: Container(width: 14, height: 14, decoration: const BoxDecoration(color: AppColors.bad, shape: BoxShape.circle)),
      );
}
