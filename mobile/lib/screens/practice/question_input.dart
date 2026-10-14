import 'package:flutter/material.dart';

import '../../models/models.dart';
import '../../theme.dart';

class QuestionInput extends StatefulWidget {
  const QuestionInput({super.key, required this.question, required this.value, required this.onChanged});
  final PracticeQuestion question;
  final String? value;
  final ValueChanged<String> onChanged;

  @override
  State<QuestionInput> createState() => _QuestionInputState();
}

class _QuestionInputState extends State<QuestionInput> {
  late final _text = TextEditingController(text: widget.value ?? '');

  @override
  void dispose() {
    _text.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final q = widget.question;
    return Padding(
      padding: const EdgeInsets.only(bottom: 18),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Container(
          width: 32,
          height: 32,
          alignment: Alignment.center,
          decoration: BoxDecoration(color: AppColors.brand50, borderRadius: BorderRadius.circular(9)),
          child: Text('${q.number}', style: const TextStyle(fontWeight: FontWeight.w800, color: AppColors.brandDark)),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(q.prompt, style: const TextStyle(fontSize: 15.5, height: 1.45)),
            const SizedBox(height: 8),
            switch (q.type) {
              'gap' => TextField(
                  controller: _text,
                  autocorrect: false,
                  enableSuggestions: false,
                  textCapitalization: TextCapitalization.none,
                  decoration: const InputDecoration(hintText: 'Type your answer', isDense: true),
                  onChanged: widget.onChanged,
                ),
              'matching' => DropdownButtonFormField<String>(
                  initialValue: (widget.value?.isEmpty ?? true) ? null : widget.value,
                  isExpanded: true,
                  decoration: const InputDecoration(isDense: true),
                  hint: const Text('Choose…'),
                  items: [
                    for (final o in q.choices)
                      DropdownMenuItem(value: o.key, child: Text('${o.key}. ${o.text}', overflow: TextOverflow.ellipsis)),
                  ],
                  onChanged: (v) => widget.onChanged(v ?? ''),
                ),
              _ => Wrap(spacing: 8, runSpacing: 8, children: [
                  for (final o in q.choices)
                    ChoiceChip(
                      label: Text(q.type == 'mcq' ? '${o.key}. ${o.text}' : o.text),
                      selected: widget.value == o.key,
                      showCheckmark: false,
                      selectedColor: AppColors.brand100,
                      side: BorderSide(color: widget.value == o.key ? AppColors.brand : AppColors.border),
                      labelStyle: TextStyle(fontWeight: widget.value == o.key ? FontWeight.w700 : FontWeight.w500, color: AppColors.ink),
                      onSelected: (_) => widget.onChanged(o.key),
                    ),
                ]),
            },
          ]),
        ),
      ]),
    );
  }
}
