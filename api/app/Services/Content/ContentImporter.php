<?php

namespace App\Services\Content;

use App\Models\PracticeQuestion;
use App\Models\PracticeTest;
use App\Models\SpeakingQuestion;
use App\Models\WritingTask;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Validates and stores practice content from seed files or AI generation.
 */
class ContentImporter
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function importSpeaking(array $items): int
    {
        $created = 0;

        foreach ($items as $item) {
            $part = (int) ($item['part'] ?? 0);
            $prompt = trim((string) ($item['prompt'] ?? ''));
            $topic = trim((string) ($item['topic'] ?? ''));

            if (! in_array($part, [1, 2, 3], true) || $prompt === '' || $topic === '') {
                continue;
            }

            $cuePoints = $part === 2 ? $this->stringList($item['cue_points'] ?? []) : null;
            if ($part === 2 && count($cuePoints) < 3) {
                continue;
            }

            $question = SpeakingQuestion::firstOrCreate(
                ['part' => $part, 'prompt' => $prompt],
                ['topic' => $topic, 'cue_points' => $cuePoints, 'is_published' => true],
            );
            $created += $question->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function importWriting(array $items): int
    {
        $created = 0;

        foreach ($items as $item) {
            $taskNumber = (int) ($item['task_number'] ?? 0);
            $testType = (string) ($item['test_type'] ?? '');
            $prompt = trim((string) ($item['prompt'] ?? ''));
            $title = trim((string) ($item['title'] ?? ''));

            if (! in_array($taskNumber, [1, 2], true) || ! in_array($testType, ['academic', 'general', 'both'], true) || $prompt === '' || $title === '') {
                continue;
            }

            $visual = $this->validVisual($item['visual'] ?? null);
            if ($taskNumber === 1 && $testType === 'academic' && $visual === null) {
                continue;
            }

            $task = WritingTask::firstOrCreate(
                ['task_number' => $taskNumber, 'prompt' => $prompt],
                ['test_type' => $testType, 'title' => $title, 'visual' => $visual, 'is_published' => true],
            );
            $created += $task->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * Store a reading or listening test. Invalid questions are dropped and the rest numbered 1..n.
     *
     * @param  array<string, mixed>  $test
     */
    public function importPracticeTest(string $module, array $test): PracticeTest
    {
        if (! in_array($module, ['reading', 'listening'], true)) {
            throw new InvalidArgumentException("Unknown module {$module}.");
        }

        $sections = array_values(array_filter((array) ($test['sections'] ?? []), 'is_array'));
        if ($sections === []) {
            throw new InvalidArgumentException('A practice test needs at least one section.');
        }

        return DB::transaction(function () use ($module, $test, $sections): PracticeTest {
            $practiceTest = PracticeTest::create([
                'module' => $module,
                'test_type' => $module === 'reading' ? (($test['test_type'] ?? 'academic') === 'general' ? 'general' : 'academic') : null,
                'title' => trim((string) ($test['title'] ?? ucfirst($module).' practice test')),
                'duration_minutes' => (int) ($test['duration_minutes'] ?? ($module === 'reading' ? 60 : 30)),
                'is_published' => true,
            ]);

            $number = 1;
            foreach ($sections as $position => $section) {
                $script = $module === 'listening' ? $this->validScript($section['script'] ?? []) : null;
                $body = $module === 'reading' ? trim((string) ($section['body'] ?? '')) : null;

                if (($module === 'listening' && $script === []) || ($module === 'reading' && $body === '')) {
                    throw new InvalidArgumentException('Section '.($position + 1).' is missing its '.($module === 'reading' ? 'passage' : 'script').'.');
                }

                $practiceSection = $practiceTest->sections()->create([
                    'position' => $position + 1,
                    'title' => trim((string) ($section['title'] ?? 'Section '.($position + 1))),
                    'instructions' => $section['instructions'] ?? null,
                    'body' => $body,
                    'script' => $script,
                ]);

                foreach ((array) ($section['questions'] ?? []) as $question) {
                    $valid = is_array($question) ? $this->validQuestion($question) : null;
                    if ($valid === null) {
                        Log::info('Skipped invalid practice question', ['test' => $practiceTest->title, 'question' => $question]);

                        continue;
                    }
                    $practiceSection->questions()->create($valid + ['number' => $number++]);
                }
            }

            if ($number === 1) {
                throw new InvalidArgumentException('The test has no valid questions.');
            }

            return $practiceTest;
        });
    }

    /**
     * @param  array<string, mixed>  $question
     * @return array<string, mixed>|null
     */
    private function validQuestion(array $question): ?array
    {
        $type = (string) ($question['type'] ?? '');
        $prompt = trim((string) ($question['prompt'] ?? ''));
        $answers = $this->stringList($question['answers'] ?? []);

        if (! in_array($type, PracticeQuestion::TYPES, true) || $prompt === '' || $answers === []) {
            return null;
        }

        $options = null;
        if (in_array($type, ['mcq', 'matching'], true)) {
            $options = [];
            foreach ((array) ($question['options'] ?? []) as $option) {
                if (is_array($option) && filled($option['key'] ?? null) && filled($option['text'] ?? null)) {
                    $options[] = ['key' => strtoupper(trim((string) $option['key'])), 'text' => trim((string) $option['text'])];
                }
            }
            $keys = array_column($options, 'key');
            $answers = array_map('strtoupper', $answers);
            if (count($options) < 2 || array_diff($answers, $keys) !== []) {
                return null;
            }
        }

        if ($type === 'tfng' || $type === 'ynng') {
            $allowed = $type === 'tfng' ? ['TRUE', 'FALSE', 'NOT GIVEN'] : ['YES', 'NO', 'NOT GIVEN'];
            $answers = array_map('strtoupper', $answers);
            if (count($answers) !== 1 || ! in_array($answers[0], $allowed, true)) {
                return null;
            }
        }

        return [
            'type' => $type,
            'prompt' => $prompt,
            'options' => $options,
            'answers' => array_values($answers),
            'explanation' => filled($question['explanation'] ?? null) ? trim((string) $question['explanation']) : null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validVisual(mixed $visual): ?array
    {
        if (! is_array($visual)) {
            return null;
        }

        $type = $visual['type'] ?? null;

        if ($type === 'table') {
            return is_array($visual['columns'] ?? null) && is_array($visual['rows'] ?? null) && $visual['rows'] !== []
                ? ['type' => 'table', 'title' => (string) ($visual['title'] ?? ''), 'columns' => array_values($visual['columns']), 'rows' => array_values($visual['rows'])]
                : null;
        }

        if (! in_array($type, ['bar', 'line', 'pie'], true) || ! is_array($visual['labels'] ?? null) || ! is_array($visual['datasets'] ?? null)) {
            return null;
        }

        $labelCount = count($visual['labels']);
        $datasets = [];
        foreach ($visual['datasets'] as $dataset) {
            if (is_array($dataset) && is_array($dataset['data'] ?? null) && count($dataset['data']) === $labelCount) {
                $datasets[] = ['label' => (string) ($dataset['label'] ?? ''), 'data' => array_map('floatval', $dataset['data'])];
            }
        }

        if ($datasets === [] || ($type === 'pie' && count($datasets) !== 1)) {
            return null;
        }

        return [
            'type' => $type,
            'title' => (string) ($visual['title'] ?? ''),
            'unit' => (string) ($visual['unit'] ?? ''),
            'labels' => array_values(array_map('strval', $visual['labels'])),
            'datasets' => $datasets,
        ];
    }

    /**
     * @return list<array{speaker: string, text: string}>
     */
    private function validScript(mixed $script): array
    {
        $lines = [];
        foreach ((array) $script as $line) {
            if (is_array($line) && filled($line['text'] ?? null)) {
                $lines[] = ['speaker' => trim((string) ($line['speaker'] ?? 'Speaker')), 'text' => trim((string) $line['text'])];
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $items): array
    {
        if (is_string($items)) {
            $items = [$items];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
            (array) $items,
        ), fn (string $item): bool => $item !== ''));
    }
}
