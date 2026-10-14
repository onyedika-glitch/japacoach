import 'dart:async';

/// Calls [tick] every [interval] while [active] returns true.
class Poller {
  Poller(this.tick, this.active, {this.interval = const Duration(seconds: 5)});
  final Future<void> Function() tick;
  final bool Function() active;
  final Duration interval;
  Timer? _timer;

  void start() {
    _timer?.cancel();
    _timer = Timer.periodic(interval, (_) async {
      if (!active()) {
        stop();
        return;
      }
      try {
        await tick();
      } catch (_) {}
    });
  }

  void stop() => _timer?.cancel();
}
