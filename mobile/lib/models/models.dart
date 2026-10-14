// Plain data classes for the Japa Coach API. Field names follow the API's JSON.

double? _d(dynamic v) => v == null ? null : (v is num ? v.toDouble() : double.tryParse(v.toString()));
int _i(dynamic v, [int fallback = 0]) => v == null ? fallback : (v is num ? v.toInt() : int.tryParse(v.toString()) ?? fallback);
DateTime? _date(dynamic v) => v == null ? null : DateTime.tryParse(v.toString())?.toLocal();
List<String> _strings(dynamic v) => v is List ? v.map((e) => e.toString()).toList() : const [];
List<T> _list<T>(dynamic v, T Function(Map<String, dynamic>) parse) =>
    v is List ? v.whereType<Map>().map((e) => parse(Map<String, dynamic>.from(e))).toList() : <T>[];

class User {
  User.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        name = j['name'] as String? ?? '',
        email = j['email'] as String? ?? '',
        avatarUrl = j['avatar_url'] as String?,
        isAdmin = j['is_admin'] == true,
        testType = j['test_type'] as String? ?? 'academic',
        targetBand = _d(j['target_band']),
        testDate = _date(j['test_date']),
        emailReminders = j['email_reminders'] != false,
        plan = j['plan'] as String? ?? 'free',
        planExpiresAt = _date(j['plan_expires_at']),
        isPremium = j['is_premium'] == true,
        referralCode = j['referral_code'] as String? ?? '',
        streakDays = _i(j['streak_days']);

  final int id;
  final String name;
  final String email;
  final String? avatarUrl;
  final bool isAdmin;
  final String testType;
  final double? targetBand;
  final DateTime? testDate;
  final bool emailReminders;
  final String plan;
  final DateTime? planExpiresAt;
  final bool isPremium;
  final String referralCode;
  final int streakDays;

  String get firstName => name.trim().split(' ').first;
}

class Usage {
  Usage.fromJson(Map<String, dynamic> j)
      : used = _i(j['used']),
        limit = j['limit'] == null ? null : _i(j['limit']);
  final int used;
  final int? limit;
}

class StudyTask {
  StudyTask.fromJson(Map<String, dynamic> j)
      : module = j['module']?.toString() ?? '',
        title = j['title']?.toString() ?? '',
        minutes = _i(j['minutes']);
  final String module;
  final String title;
  final int minutes;
}

class StudyPlan {
  StudyPlan.fromJson(Map<String, dynamic> j)
      : generatedAt = _date(j['generated_at']),
        summary = j['summary']?.toString() ?? '',
        weeks = _list(j['weeks'], StudyWeek.fromJson),
        tips = _strings(j['tips']);
  final DateTime? generatedAt;
  final String summary;
  final List<StudyWeek> weeks;
  final List<String> tips;
}

class StudyWeek {
  StudyWeek.fromJson(Map<String, dynamic> j)
      : week = _i(j['week']),
        focus = j['focus']?.toString() ?? '',
        days = _list(j['days'], (d) => (day: d['day']?.toString() ?? '', tasks: _list(d['tasks'], StudyTask.fromJson)));
  final int week;
  final String focus;
  final List<({String day, List<StudyTask> tasks})> days;
}

class Dashboard {
  Dashboard.fromJson(Map<String, dynamic> j)
      : user = User.fromJson(Map<String, dynamic>.from(j['user'] as Map)),
        usage = (j['usage'] as Map? ?? {}).map((k, v) => MapEntry(k.toString(), Usage.fromJson(Map<String, dynamic>.from(v as Map)))),
        latest = (j['latest'] as Map? ?? {}).map((k, v) => MapEntry(k.toString(), _d(v))),
        history = _list(j['history'], (h) => (date: _date(h['date']) ?? DateTime.now(), module: h['module'].toString(), band: _d(h['band']) ?? 0)),
        recent = _list(j['recent_attempts'], RecentAttempt.fromJson),
        studyPlan = j['study_plan'] is Map ? StudyPlan.fromJson(Map<String, dynamic>.from(j['study_plan'] as Map)) : null,
        daysToTest = j['days_to_test'] == null ? null : _i(j['days_to_test']);
  final User user;
  final Map<String, Usage> usage;
  final Map<String, double?> latest;
  final List<({DateTime date, String module, double band})> history;
  final List<RecentAttempt> recent;
  final StudyPlan? studyPlan;
  final int? daysToTest;
}

class RecentAttempt {
  RecentAttempt.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        module = j['module'].toString(),
        title = j['title']?.toString() ?? '',
        band = _d(j['band']),
        status = j['status']?.toString() ?? '',
        createdAt = _date(j['created_at']);
  final int id;
  final String module;
  final String title;
  final double? band;
  final String status;
  final DateTime? createdAt;
}

class CriterionScore {
  CriterionScore.fromJson(Map<String, dynamic> j)
      : band = _d(j['band']) ?? 0,
        comment = j['comment']?.toString() ?? '';
  final double band;
  final String comment;
}

Map<String, CriterionScore>? _scores(dynamic v) => v is Map
    ? v.map((k, s) => MapEntry(k.toString(), CriterionScore.fromJson(Map<String, dynamic>.from(s as Map))))
    : null;

class Correction {
  Correction.fromJson(Map<String, dynamic> j)
      : original = j['original']?.toString() ?? '',
        corrected = j['corrected']?.toString() ?? '',
        explanation = j['explanation']?.toString() ?? '';
  final String original;
  final String corrected;
  final String explanation;
}

class SpeakingAnswer {
  SpeakingAnswer.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        status = j['status']?.toString() ?? '',
        durationSeconds = _d(j['duration_seconds']) ?? 0,
        transcript = j['transcript'] as String?,
        audioUrl = j['audio_url'] as String?;
  final int id;
  final String status;
  final double durationSeconds;
  final String? transcript;
  final String? audioUrl;
}

class SpeakingQuestion {
  SpeakingQuestion.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        part = _i(j['part'], 1),
        position = _i(j['position']),
        prompt = j['prompt']?.toString() ?? '',
        cuePoints = _strings(j['cue_points']),
        audioUrl = j['audio_url'] as String?,
        answer = j['answer'] is Map ? SpeakingAnswer.fromJson(Map<String, dynamic>.from(j['answer'] as Map)) : null;
  final int id;
  final int part;
  final int position;
  final String prompt;
  final List<String> cuePoints;
  final String? audioUrl;
  final SpeakingAnswer? answer;
}

class SpeakingFeedback {
  SpeakingFeedback.fromJson(Map<String, dynamic> j)
      : summary = j['summary']?.toString() ?? '',
        strengths = _strings(j['strengths']),
        improvements = _strings(j['improvements']),
        corrections = _list(j['corrections'], Correction.fromJson),
        betterAnswers = _list(j['better_answers'], (b) => (question: b['question']?.toString() ?? '', answer: b['answer']?.toString() ?? '')),
        pronunciationTips = _list(j['pronunciation_tips'], (t) => (word: t['word']?.toString() ?? '', tip: t['tip']?.toString() ?? '')),
        metrics = j['metrics'] is Map ? Map<String, dynamic>.from(j['metrics'] as Map) : null;
  final String summary;
  final List<String> strengths;
  final List<String> improvements;
  final List<Correction> corrections;
  final List<({String question, String answer})> betterAnswers;
  final List<({String word, String tip})> pronunciationTips;
  final Map<String, dynamic>? metrics;
}

class SpeakingAttempt {
  SpeakingAttempt.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        mode = j['mode']?.toString() ?? 'full',
        status = j['status']?.toString() ?? '',
        overallBand = _d(j['overall_band']),
        scores = _scores(j['scores']),
        feedback = j['feedback'] is Map ? SpeakingFeedback.fromJson(Map<String, dynamic>.from(j['feedback'] as Map)) : null,
        createdAt = _date(j['created_at']),
        scoredAt = _date(j['scored_at']),
        questions = _list(j['questions'], SpeakingQuestion.fromJson)..sort((a, b) => a.part != b.part ? a.part - b.part : a.position - b.position);
  final int id;
  final String mode;
  final String status;
  final double? overallBand;
  final Map<String, CriterionScore>? scores;
  final SpeakingFeedback? feedback;
  final DateTime? createdAt;
  final DateTime? scoredAt;
  final List<SpeakingQuestion> questions;

  String get modeTitle => const {'full': 'Full speaking mock', 'part1': 'Part 1 drill', 'part2': 'Part 2 drill', 'part3': 'Part 3 drill'}[mode] ?? 'Speaking test';
}

class WritingTask {
  WritingTask.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        taskNumber = _i(j['task_number'], 2),
        testType = j['test_type']?.toString() ?? 'both',
        title = j['title']?.toString() ?? '',
        prompt = j['prompt']?.toString() ?? '',
        visual = j['visual'] is Map ? Map<String, dynamic>.from(j['visual'] as Map) : null,
        minWords = _i(j['min_words'], 150),
        recommendedMinutes = _i(j['recommended_minutes'], 20);
  final int id;
  final int taskNumber;
  final String testType;
  final String title;
  final String prompt;
  final Map<String, dynamic>? visual;
  final int minWords;
  final int recommendedMinutes;
}

class WritingAttempt {
  WritingAttempt.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        task = WritingTask.fromJson(Map<String, dynamic>.from(j['task'] as Map? ?? {})),
        essay = j['essay']?.toString() ?? '',
        wordCount = _i(j['word_count']),
        status = j['status']?.toString() ?? '',
        overallBand = _d(j['overall_band']),
        scores = _scores(j['scores']),
        summary = (j['feedback'] as Map?)?['summary']?.toString(),
        strengths = _strings((j['feedback'] as Map?)?['strengths']),
        improvements = _strings((j['feedback'] as Map?)?['improvements']),
        corrections = _list((j['feedback'] as Map?)?['corrections'], Correction.fromJson),
        modelAnswer = (j['feedback'] as Map?)?['model_answer']?.toString(),
        createdAt = _date(j['created_at']);
  final int id;
  final WritingTask task;
  final String essay;
  final int wordCount;
  final String status;
  final double? overallBand;
  final Map<String, CriterionScore>? scores;
  final String? summary;
  final List<String> strengths;
  final List<String> improvements;
  final List<Correction> corrections;
  final String? modelAnswer;
  final DateTime? createdAt;
}

class PracticeOption {
  PracticeOption(this.key, this.text);
  final String key;
  final String text;
}

class PracticeQuestion {
  PracticeQuestion.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        number = _i(j['number']),
        type = j['type']?.toString() ?? 'gap',
        prompt = j['prompt']?.toString() ?? '',
        options = _list(j['options'], (o) => PracticeOption(o['key'].toString(), o['text']?.toString() ?? ''));
  final int id;
  final int number;
  final String type;
  final String prompt;
  final List<PracticeOption> options;

  /// Options to show: True/False/Not given style questions have fixed choices.
  List<PracticeOption> get choices {
    if (options.isNotEmpty) return options;
    if (type == 'tfng') return [PracticeOption('TRUE', 'True'), PracticeOption('FALSE', 'False'), PracticeOption('NOT GIVEN', 'Not given')];
    if (type == 'ynng') return [PracticeOption('YES', 'Yes'), PracticeOption('NO', 'No'), PracticeOption('NOT GIVEN', 'Not given')];
    return const [];
  }
}

class PracticeSection {
  PracticeSection.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        position = _i(j['position']),
        title = j['title']?.toString() ?? '',
        instructions = j['instructions']?.toString() ?? '',
        body = j['body'] as String?,
        audioUrl = j['audio_url'] as String?,
        transcript = _list(j['transcript'], (t) => (speaker: t['speaker']?.toString() ?? '', text: t['text']?.toString() ?? '')),
        questions = _list(j['questions'], PracticeQuestion.fromJson);
  final int id;
  final int position;
  final String title;
  final String instructions;
  final String? body;
  final String? audioUrl;
  final List<({String speaker, String text})> transcript;
  final List<PracticeQuestion> questions;
}

class PracticeTestSummary {
  PracticeTestSummary.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        title = j['title']?.toString() ?? '',
        testType = j['test_type']?.toString() ?? 'both',
        questionCount = _i(j['question_count']),
        durationMinutes = _i(j['duration_minutes'], 60),
        bestBand = _d(j['best_band']),
        attemptsCount = _i(j['attempts_count']);
  final int id;
  final String title;
  final String testType;
  final int questionCount;
  final int durationMinutes;
  final double? bestBand;
  final int attemptsCount;
}

class ReviewItem {
  ReviewItem.fromJson(Map<String, dynamic> j)
      : questionId = _i(j['question_id']),
        number = _i(j['number']),
        yourAnswer = j['your_answer'] is List ? (j['your_answer'] as List).join(', ') : j['your_answer']?.toString(),
        correctAnswers = _strings(j['correct_answers']),
        isCorrect = j['is_correct'] == true,
        explanation = j['explanation'] as String?;
  final int questionId;
  final int number;
  final String? yourAnswer;
  final List<String> correctAnswers;
  final bool isCorrect;
  final String? explanation;
}

class PracticeAttempt {
  PracticeAttempt.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        module = j['module']?.toString() ?? 'reading',
        status = j['status']?.toString() ?? '',
        startedAt = _date(j['started_at']) ?? DateTime.now(),
        submittedAt = _date(j['submitted_at']),
        answers = (j['answers'] is Map ? Map<String, dynamic>.from(j['answers'] as Map) : <String, dynamic>{})
            .map((k, v) => MapEntry(k, v is List ? v.join(', ') : v?.toString() ?? '')),
        correctCount = j['correct_count'] == null ? null : _i(j['correct_count']),
        totalCount = _i(j['total_count']),
        band = _d(j['band']),
        testTitle = (j['test'] as Map?)?['title']?.toString() ?? '',
        durationMinutes = _i((j['test'] as Map?)?['duration_minutes'], 60),
        sections = _list((j['test'] as Map?)?['sections'], PracticeSection.fromJson)..sort((a, b) => a.position - b.position),
        review = j['review'] is List ? _list(j['review'], ReviewItem.fromJson) : null;
  final int id;
  final String module;
  final String status;
  final DateTime startedAt;
  final DateTime? submittedAt;
  final Map<String, String> answers;
  final int? correctCount;
  final int totalCount;
  final double? band;
  final String testTitle;
  final int durationMinutes;
  final List<PracticeSection> sections;
  final List<ReviewItem>? review;

  bool get isSubmitted => status == 'submitted';
}

class MockPart {
  MockPart.fromJson(Map<String, dynamic> j)
      : status = j['status']?.toString() ?? 'not_started',
        band = _d(j['band']),
        raw = j;
  final String status;
  final double? band;
  final Map<String, dynamic> raw;

  int? id(String key) => raw[key] == null ? null : _i(raw[key]);
}

class MockExam {
  MockExam.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        status = j['status']?.toString() ?? 'in_progress',
        testType = j['test_type']?.toString() ?? 'academic',
        overallBand = _d(j['overall_band']),
        createdAt = _date(j['created_at']),
        parts = (j['parts'] as Map? ?? {}).map((k, v) => MapEntry(k.toString(), MockPart.fromJson(Map<String, dynamic>.from(v as Map))));
  final int id;
  final String status;
  final String testType;
  final double? overallBand;
  final DateTime? createdAt;
  final Map<String, MockPart> parts;
}

class Review {
  Review.fromJson(Map<String, dynamic> j)
      : id = _i(j['id']),
        rating = _i(j['rating']),
        comment = j['comment']?.toString() ?? '',
        displayName = j['display_name']?.toString() ?? '',
        location = j['location'] as String?,
        module = j['module'] as String?,
        band = _d(j['band']),
        canPublish = j['can_publish'] == true,
        status = j['status'] as String?;
  final int id;
  final int rating;
  final String comment;
  final String displayName;
  final String? location;
  final String? module;
  final double? band;
  final bool canPublish;
  final String? status;
}
