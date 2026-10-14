import 'package:flutter_test/flutter_test.dart';
import 'package:japa_coach/models/models.dart';
import 'package:japa_coach/widgets/format.dart';

void main() {
  test('speaking attempt parses and sorts questions by part', () {
    final a = SpeakingAttempt.fromJson({
      'id': 7,
      'mode': 'full',
      'status': 'scored',
      'overall_band': '6.5',
      'scores': {
        'fluency_coherence': {'band': 6.5, 'comment': 'Good flow'},
      },
      'questions': [
        {'id': 2, 'part': 2, 'position': 1, 'prompt': 'Describe a place', 'cue_points': ['where'], 'answer': null},
        {'id': 1, 'part': 1, 'position': 1, 'prompt': 'Where do you live?', 'answer': {'id': 9, 'status': 'transcribed', 'duration_seconds': 12.5}},
      ],
    });
    expect(a.overallBand, 6.5);
    expect(a.questions.map((q) => q.id), [1, 2]);
    expect(a.questions.first.answer?.durationSeconds, 12.5);
    expect(a.scores!['fluency_coherence']!.comment, 'Good flow');
  });

  test('true/false questions get fixed choices', () {
    final q = PracticeQuestion.fromJson({'id': 1, 'number': 1, 'type': 'tfng', 'prompt': 'x', 'options': null});
    expect(q.choices.map((c) => c.key), ['TRUE', 'FALSE', 'NOT GIVEN']);
  });

  test('formatting helpers', () {
    expect(clock(125), '2:05');
    expect(wordCount('  one two\nthree '), 3);
    expect(band(null), 'N/A');
  });
}
