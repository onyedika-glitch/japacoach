import 'package:flutter/material.dart';

import '../api/api_client.dart';
import 'common.dart';

/// Loads data once, shows loading/error states, and supports pull-to-refresh via [reload].
class AsyncLoader<T> extends StatefulWidget {
  const AsyncLoader({super.key, required this.load, required this.builder, this.loadingLabel});
  final Future<T> Function() load;
  final Widget Function(BuildContext context, T data, Future<void> Function() reload) builder;
  final String? loadingLabel;

  @override
  State<AsyncLoader<T>> createState() => _AsyncLoaderState<T>();
}

class _AsyncLoaderState<T> extends State<AsyncLoader<T>> {
  T? _data;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  Future<void> _reload() async {
    try {
      final data = await widget.load();
      if (!mounted) return;
      setState(() {
        _data = data;
        _error = null;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e is ApiException ? e.message : 'Something went wrong. Please try again.';
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return LoadingView(label: widget.loadingLabel);
    if (_data == null) {
      return ErrorView(
        message: _error ?? 'Could not load this page.',
        onRetry: () {
          setState(() => _loading = true);
          _reload();
        },
      );
    }
    return widget.builder(context, _data as T, _reload);
  }
}
