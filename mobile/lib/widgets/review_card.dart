import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../models/models.dart';
import '../state/auth.dart';
import '../theme.dart';
import 'common.dart';

/// "How is Japa Coach helping you?" Shown under reports until the user leaves a review or closes it,
/// and always on the settings screen ([standalone]).
class ReviewCard extends StatefulWidget {
  const ReviewCard({super.key, this.module, this.standalone = false});
  final String? module;
  final bool standalone;

  @override
  State<ReviewCard> createState() => _ReviewCardState();
}

class _ReviewCardState extends State<ReviewCard> {
  // Remembered for the app session; the server knows whether a review exists.
  static bool _dismissed = false;

  bool _loaded = false;
  bool _offered = false;
  bool _saved = false;
  bool _saving = false;
  Review? _existing;
  String? _error;
  int _rating = 0;
  bool _canPublish = false;
  final _comment = TextEditingController();
  final _name = TextEditingController();
  final _city = TextEditingController();

  @override
  void initState() {
    super.initState();
    _name.text = context.read<AuthState>().user?.firstName ?? '';
    _load();
  }

  Future<void> _load() async {
    try {
      final data = await context.read<ApiClient>().get('/api/me/review');
      if (data['review'] is Map) {
        final r = Review.fromJson(Map<String, dynamic>.from(data['review'] as Map));
        _existing = r;
        _rating = r.rating;
        _comment.text = r.comment;
        _name.text = r.displayName;
        _city.text = r.location ?? '';
        _canPublish = r.canPublish;
      }
    } catch (_) {
      if (!widget.standalone) return;
    }
    if (!mounted) return;
    setState(() {
      _offered = widget.standalone || (_existing == null && !_dismissed);
      _loaded = true;
    });
  }

  Future<void> _submit() async {
    if (_comment.text.trim().length < 10) {
      setState(() => _error = 'Please write at least a sentence.');
      return;
    }
    if (_name.text.trim().isEmpty) {
      setState(() => _error = 'Add the name to show with your review.');
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final data = await context.read<ApiClient>().put('/api/me/review', data: {
        'rating': _rating,
        'comment': _comment.text.trim(),
        'display_name': _name.text.trim(),
        'location': _city.text.trim().isEmpty ? null : _city.text.trim(),
        'module': widget.module ?? _existing?.module,
        'can_publish': _canPublish,
      });
      _existing = Review.fromJson(Map<String, dynamic>.from(data['review'] as Map));
      setState(() => _saved = true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _comment.dispose();
    _name.dispose();
    _city.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_loaded || !_offered || (!widget.standalone && _dismissed && !_saved)) return const SizedBox.shrink();
    return Padding(
      padding: EdgeInsets.only(top: widget.standalone ? 0 : 24),
      child: AppCard(
        color: widget.standalone ? null : AppColors.brand50,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Expanded(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text(widget.standalone ? 'Your review' : 'How is Japa Coach helping you?', style: grotesk(size: 17)),
                const SizedBox(height: 4),
                Text(
                  widget.standalone
                      ? (_existing != null ? 'Edit your review any time. Changes are checked again before they appear.' : 'Tell other test takers what Japa Coach is like.')
                      : 'Rate your experience. Your review helps other Nigerians preparing for IELTS.',
                  style: const TextStyle(color: AppColors.muted, fontSize: 14),
                ),
              ]),
            ),
            if (!widget.standalone && !_saved)
              IconButton(
                tooltip: 'Not now',
                icon: const Icon(Icons.close_rounded, color: AppColors.muted),
                onPressed: () => setState(() => _dismissed = true),
              ),
          ]),
          const SizedBox(height: 12),
          if (_saved)
            Notice(
              'Thank you! ${_canPublish ? 'We will review it and may feature it on our website.' : 'Only the Japa Coach team will see it.'}',
              tone: 'success',
            )
          else ...[
            Row(children: [
              for (var n = 1; n <= 5; n++)
                IconButton(
                  visualDensity: VisualDensity.compact,
                  tooltip: '$n star${n > 1 ? 's' : ''}',
                  onPressed: () => setState(() => _rating = n),
                  icon: Icon(n <= _rating ? Icons.star_rounded : Icons.star_outline_rounded, size: 36, color: n <= _rating ? AppColors.accent : AppColors.border),
                ),
            ]),
            if (_rating > 0) ...[
              const SizedBox(height: 8),
              TextField(
                controller: _comment,
                maxLines: 4,
                maxLength: 600,
                decoration: InputDecoration(
                  labelText: 'Your review',
                  hintText: _rating >= 4 ? 'What helped you most? Did your band improve?' : 'What should we do better? We read every review.',
                ),
              ),
              const SizedBox(height: 8),
              Row(children: [
                Expanded(child: TextField(controller: _name, maxLength: 40, decoration: const InputDecoration(labelText: 'Name to show', counterText: ''))),
                const SizedBox(width: 10),
                Expanded(child: TextField(controller: _city, maxLength: 40, decoration: const InputDecoration(labelText: 'City (optional)', counterText: ''))),
              ]),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                value: _canPublish,
                onChanged: (v) => setState(() => _canPublish = v ?? false),
                title: const Text('Japa Coach may show my review, name, city and estimated band on its website.', style: TextStyle(fontSize: 14)),
              ),
              if (_error != null) ...[Notice(_error!, tone: 'error'), const SizedBox(height: 10)],
              FilledButton(onPressed: _saving ? null : _submit, child: Text(_saving ? 'Sending…' : (_existing != null ? 'Update review' : 'Send review'))),
            ],
          ],
        ]),
      ),
    );
  }
}
