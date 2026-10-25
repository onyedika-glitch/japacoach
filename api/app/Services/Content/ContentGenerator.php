<?php

namespace App\Services\Content;

use App\Models\PracticeTest;
use App\Models\SpeakingQuestion;
use App\Models\WritingTask;
use App\Services\Ai\DeepSeekClient;
use App\Support\Text;

/**
 * Generates original IELTS-style practice content with DeepSeek and stores it through the importer.
 */
class ContentGenerator
{
    private const SYSTEM = 'You are a senior IELTS test writer. Write ORIGINAL material at real IELTS difficulty and never copy published IELTS or Cambridge material. Answers must be unambiguous and verifiable. '.Text::NO_DASHES.' Reply with JSON only.';

    private const QUESTION_RULES = <<<'TEXT'
Question object: {"type": "mcq"|"tfng"|"ynng"|"gap"|"matching", "prompt": "...", "options": [{"key": "A", "text": "..."}] or null, "answers": ["..."], "explanation": "where/why in the text"}
- mcq and matching: 3-5 options, answers = [correct key].
- tfng: options null, answers exactly one of ["TRUE"], ["FALSE"], ["NOT GIVEN"]. ynng the same with YES/NO/NOT GIVEN (only for writer's-opinion texts).
- gap: options null, prompt contains "______" and a word limit such as "NO MORE THAN TWO WORDS"; answers = the exact word(s) from the text plus acceptable spelling variants.
TEXT;

    public function __construct(
        private DeepSeekClient $ai,
        private ContentImporter $importer,
    ) {}

    /**
     * Generate new Part 1 topics and Part 2 cue cards with linked Part 3 questions.
     */
    public function speaking(int $topicCount = 4): int
    {
        $existing = SpeakingQuestion::distinct()->pluck('topic')->implode(', ');

        $result = $this->ai->json(self::SYSTEM, <<<PROMPT
Create IELTS Speaking material for {$topicCount} NEW Part 1 topics and {$topicCount} NEW Part 2 cue cards. Avoid these existing topics: {$existing}.
JSON: {"part1": [{"topic": "...", "questions": ["4 short everyday questions"]}],
       "part2": [{"topic": "...", "prompt": "Describe ...", "cue_points": ["what ...", "when ...", "who ...", "and explain ..."], "part3": ["3 abstract discussion questions on the same theme"]}]}
PROMPT, 0.8);

        $items = [];
        foreach ((array) ($result['part1'] ?? []) as $set) {
            foreach ((array) ($set['questions'] ?? []) as $question) {
                $items[] = ['part' => 1, 'topic' => $set['topic'] ?? '', 'prompt' => $question];
            }
        }
        foreach ((array) ($result['part2'] ?? []) as $card) {
            $items[] = ['part' => 2, 'topic' => $card['topic'] ?? '', 'prompt' => $card['prompt'] ?? '', 'cue_points' => $card['cue_points'] ?? []];
            foreach ((array) ($card['part3'] ?? []) as $question) {
                $items[] = ['part' => 3, 'topic' => $card['topic'] ?? '', 'prompt' => $question];
            }
        }

        return $this->importer->importSpeaking($items);
    }

    /**
     * Generate writing tasks: a balanced batch, or only General Training Task 1 letters.
     */
    public function writing(int $count = 4, string $testType = 'academic'): int
    {
        $existing = WritingTask::pluck('title')->implode('; ');

        $mix = $testType === 'general'
            ? 'all General Training Task 1 letters (test_type "general", visual null): a mix of formal, semi-formal and informal letters about everyday situations such as complaints, requests, apologies, job enquiries, invitations and thanks, each with three bullet points to cover and "Begin your letter as follows: Dear ..."'
            : 'roughly a third Academic Task 1 (with chart data), a sixth General Training Task 1 letters and half Task 2 essays (test_type "both")';

        $result = $this->ai->json(self::SYSTEM, <<<PROMPT
Create {$count} NEW IELTS writing tasks: {$mix}. Avoid these existing titles: {$existing}.
JSON: {"tasks": [{"task_number": 1|2, "test_type": "academic"|"general"|"both", "title": "short title", "prompt": "full task instructions as in the real test, ending with the word requirement", "visual": null or chart}]}
Chart (Academic Task 1 only): {"type": "bar"|"line"|"pie", "title": "...", "unit": "...", "labels": [...], "datasets": [{"label": "...", "data": [numbers, same length as labels]}]} (pie has one dataset) or {"type": "table", "title": "...", "columns": [...], "rows": [[...]]}.
PROMPT, 0.8, 6000);

        return $this->importer->importWriting((array) ($result['tasks'] ?? []));
    }

    /**
     * Generate a full three-passage reading test (one passage per request to stay within output limits).
     */
    public function reading(string $testType = 'academic'): PracticeTest
    {
        $sections = [];
        $styles = $testType === 'general'
            ? ['a set of short everyday notices or advertisements', 'a workplace document such as a staff guide or job information', 'a longer general-interest magazine article']
            : ['a scientific or natural-world article', 'a history, society or technology article', 'an argumentative academic article presenting the writer\'s views'];

        foreach ($styles as $index => $style) {
            $sections[] = $this->ai->json(self::SYSTEM, 'Write IELTS '.($testType === 'general' ? 'General Training' : 'Academic').' Reading section '.($index + 1).": {$style}, 650-850 words, with 13 questions mixing at least two question types, in passage order.\n"
                .self::QUESTION_RULES
                ."\nJSON: {\"title\": \"passage title\", \"instructions\": \"instructions for the candidate\", \"body\": \"passage text with paragraphs separated by \\n\\n\", \"questions\": [question objects]}", 0.8, 7000);
        }

        $title = ($sections[0]['title'] ?? 'Reading test').': '.ucfirst($testType).' Reading';

        return $this->importer->importPracticeTest('reading', [
            'title' => $title,
            'test_type' => $testType,
            'duration_minutes' => 60,
            'sections' => $sections,
        ]);
    }

    /**
     * Generate a four-section listening test script with questions.
     */
    public function listening(): PracticeTest
    {
        $briefs = [
            'Section 1: an everyday conversation between two people (e.g. a booking or enquiry). Form or note completion with names spelled letter by letter and numbers.',
            'Section 2: a monologue in an everyday social context (e.g. a guide describing a facility or event).',
            'Section 3: a conversation between two or three students and/or a tutor about an assignment or course.',
            'Section 4: a university lecture monologue on an academic subject.',
        ];

        $sections = [];
        foreach ($briefs as $brief) {
            $sections[] = $this->ai->json(self::SYSTEM, "Write an IELTS Listening {$brief} The script is 550-750 words of natural spoken English (fillers, corrections and distractors where the speaker changes an answer). Then write 10 questions (mcq, gap or matching) whose answers occur in script order; gap answers must appear word for word in the script.\n"
                .self::QUESTION_RULES
                ."\nUse consistent speaker labels such as \"Woman\", \"Man\", \"Tutor\", \"Lecturer\"."
                ."\nJSON: {\"title\": \"short section title\", \"instructions\": \"instructions for the candidate\", \"script\": [{\"speaker\": \"...\", \"text\": \"...\"}], \"questions\": [question objects]}", 0.8, 7000);
        }

        return $this->importer->importPracticeTest('listening', [
            'title' => ($sections[0]['title'] ?? 'Listening').': Listening test',
            'duration_minutes' => 30,
            'sections' => $sections,
        ]);
    }
}
