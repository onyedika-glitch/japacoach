import 'package:intl/intl.dart';

String band(double? value) => value == null ? 'N/A' : value.toStringAsFixed(1);

String formatDate(DateTime? d) => d == null ? 'N/A' : DateFormat('d MMM yyyy').format(d);

String formatDateTime(DateTime? d) => d == null ? 'N/A' : DateFormat('d MMM, HH:mm').format(d);

String clock(num totalSeconds) {
  final s = totalSeconds.abs().round();
  return '${totalSeconds < 0 ? '-' : ''}${s ~/ 60}:${(s % 60).toString().padLeft(2, '0')}';
}

int wordCount(String text) {
  final t = text.trim();
  return t.isEmpty ? 0 : t.split(RegExp(r'\s+')).length;
}

const moduleLabels = {
  'speaking': 'Speaking',
  'writing': 'Writing',
  'reading': 'Reading',
  'listening': 'Listening',
  'overall': 'Overall',
};

const criterionLabels = {
  'fluency_coherence': 'Fluency & Coherence',
  'lexical_resource': 'Lexical Resource',
  'grammatical_range_accuracy': 'Grammatical Range & Accuracy',
  'pronunciation': 'Pronunciation',
  'task_achievement': 'Task Achievement',
  'task_response': 'Task Response',
  'coherence_cohesion': 'Coherence & Cohesion',
};

String planLabel(String plan) => const {'free': 'Free', 'weekly': 'Weekly', 'monthly': 'Monthly', 'sprint': 'Exam Sprint'}[plan] ?? plan;
