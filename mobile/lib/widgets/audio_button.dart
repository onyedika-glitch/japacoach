import 'dart:async';

import 'package:flutter/material.dart';
import 'package:just_audio/just_audio.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../config.dart';
import '../theme.dart';
import 'format.dart';

/// A compact play/pause control with progress for one audio URL (sends the login token for private files).
class AudioPlayerBar extends StatefulWidget {
  const AudioPlayerBar({super.key, required this.url, this.label});
  final String url;
  final String? label;

  @override
  State<AudioPlayerBar> createState() => _AudioPlayerBarState();
}

class _AudioPlayerBarState extends State<AudioPlayerBar> {
  final _player = AudioPlayer();
  bool _loaded = false;
  bool _failed = false;

  Future<void> _toggle() async {
    if (_player.playing) {
      await _player.pause();
      return;
    }
    try {
      if (!_loaded) {
        final token = context.read<ApiClient>().token;
        await _player.setUrl(AppConfig.absolute(widget.url), headers: token == null ? null : {'Authorization': 'Bearer $token'});
        _loaded = true;
      }
      if (_player.processingState == ProcessingState.completed) await _player.seek(Duration.zero);
      unawaited(_player.play());
    } catch (_) {
      if (mounted) setState(() => _failed = true);
    }
  }

  @override
  void dispose() {
    _player.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_failed) return const Text('Audio unavailable.', style: TextStyle(color: AppColors.muted, fontSize: 13));
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
      decoration: BoxDecoration(color: AppColors.brand50, borderRadius: BorderRadius.circular(14)),
      child: Row(children: [
        StreamBuilder<PlayerState>(
          stream: _player.playerStateStream,
          builder: (_, snap) {
            final s = snap.data;
            final loading = s?.processingState == ProcessingState.loading || s?.processingState == ProcessingState.buffering;
            return IconButton(
              onPressed: _toggle,
              icon: loading
                  ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5))
                  : Icon((s?.playing ?? false) && s?.processingState != ProcessingState.completed ? Icons.pause_rounded : Icons.play_arrow_rounded,
                      color: AppColors.brandDark, size: 30),
            );
          },
        ),
        Expanded(
          child: StreamBuilder<Duration>(
            stream: _player.positionStream,
            builder: (_, snap) {
              final pos = snap.data ?? Duration.zero;
              final total = _player.duration ?? Duration.zero;
              final value = total.inMilliseconds == 0 ? 0.0 : (pos.inMilliseconds / total.inMilliseconds).clamp(0.0, 1.0);
              return Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                if (widget.label != null) Text(widget.label!, style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                const SizedBox(height: 4),
                ClipRRect(
                  borderRadius: BorderRadius.circular(4),
                  child: LinearProgressIndicator(value: value, minHeight: 5, backgroundColor: Colors.white, color: AppColors.brand),
                ),
                const SizedBox(height: 4),
                Text('${clock(pos.inSeconds)} / ${clock(total.inSeconds)}', style: const TextStyle(fontSize: 11, color: AppColors.muted)),
              ]);
            },
          ),
        ),
        const SizedBox(width: 10),
      ]),
    );
  }
}
