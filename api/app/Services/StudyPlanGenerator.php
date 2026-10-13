<?php

namespace App\Services;

use App\Exceptions\ExternalServiceException;
use App\Models\StudyPlan;
use App\Models\User;
use App\Services\Ai\DeepSeekClient;
use App\Support\Text;
use Illuminate\Support\Facades\Log;

/**
 * Builds a week-by-week study plan from the user's test date, target band and latest module bands.
 */
class StudyPlanGenerator
{
    private const MODULES = ['speaking', 'writing', 'reading', 'listening'];

    public function __construct(
        private DeepSeekClient $ai,
        private ProgressService $progress,
    ) {}

    public function generate(User $user): StudyPlan
    {
        $daysLeft = max(1, (int) now()->startOfDay()->diffInDays($user->test_date, false));
        $weeks = (int) min(8, max(1, ceil($daysLeft / 7)));
        $latest = $this->progress->latestBands($user);

        try {
            $plan = $this->fromAi($user, $daysLeft, $weeks, $latest);
        } catch (ExternalServiceException $exception) {
            Log::warning('Study plan AI generation failed; using rule-based plan', ['user_id' => $user->id, 'error' => $exception->getMessage()]);
            $plan = $this->ruleBased($user, $weeks, $latest);
        }

        $plan['generated_at'] = now()->toIso8601String();

        return StudyPlan::updateOrCreate(['user_id' => $user->id], ['plan' => $plan]);
    }

    /**
     * @param  array<string, float|null>  $latest
     * @return array<string, mixed>
     */
    private function fromAi(User $user, int $daysLeft, int $weeks, array $latest): array
    {
        $bands = collect($latest)->map(fn (?float $band, string $module): string => "{$module}: ".($band ?? 'not yet tested'))->implode(', ');

        $result = $this->ai->json(
            'You are an expert IELTS coach creating realistic study plans for busy Nigerian candidates who study on their phones. '.Text::NO_DASHES.' Reply with JSON only.',
            "Candidate: {$user->test_type} IELTS, target overall band {$user->target_band}, test in {$daysLeft} days.\n"
            ."Latest estimated bands: {$bands}.\n"
            ."Create a {$weeks}-week plan (5 study days per week, 30-75 minutes per day) prioritising the weakest modules and anything below target.\n"
            .'Tasks must be things the candidate can do in this app: speaking drills (part 1/2/3), full speaking mock, writing Task 1/Task 2 practice, reading tests, listening tests, full mock exam (include one in the final week if there is time), plus short self-study tasks such as vocabulary review.'
            ."\nJSON shape: {\"summary\": \"2-3 sentences\", \"weeks\": [{\"week\": 1, \"focus\": \"short focus\", \"days\": [{\"day\": \"Monday\", \"tasks\": [{\"module\": \"speaking|writing|reading|listening|general\", \"title\": \"short task\", \"minutes\": 20}]}]}], \"tips\": [\"4-6 practical tips\"]}",
            0.5,
            6000,
        );

        if (! isset($result['weeks']) || ! is_array($result['weeks']) || $result['weeks'] === []) {
            throw new ExternalServiceException('Study plan response had no weeks.');
        }

        return Text::withoutDashesDeep([
            'summary' => (string) ($result['summary'] ?? ''),
            'weeks' => array_slice(array_values($result['weeks']), 0, $weeks),
            'tips' => array_values(array_filter((array) ($result['tips'] ?? []), 'is_string')),
        ]);
    }

    /**
     * @param  array<string, float|null>  $latest
     * @return array<string, mixed>
     */
    private function ruleBased(User $user, int $weeks, array $latest): array
    {
        $ordered = collect(self::MODULES)->sortBy(fn (string $module): float => $latest[$module] ?? 0)->values();
        $weakest = $ordered->first();
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Saturday'];
        $tasks = [
            'speaking' => ['title' => 'Speaking drill (Part 1 and Part 3)', 'minutes' => 20],
            'writing' => ['title' => 'Writing Task 2 essay', 'minutes' => 45],
            'reading' => ['title' => 'Reading practice test', 'minutes' => 60],
            'listening' => ['title' => 'Listening practice test', 'minutes' => 35],
        ];

        $plan = [];
        for ($week = 1; $week <= $weeks; $week++) {
            $schedule = [];
            foreach ($days as $index => $day) {
                $module = $index === 4 ? $weakest : $ordered[$index % 4];
                $schedule[] = ['day' => $day, 'tasks' => [
                    ['module' => $module] + $tasks[$module],
                    ['module' => 'general', 'title' => 'Learn and use 10 new topic words', 'minutes' => 10],
                ]];
            }
            if ($week === $weeks) {
                $schedule[4]['tasks'] = [['module' => 'general', 'title' => 'Full mock exam', 'minutes' => 170]];
            }
            $plan[] = ['week' => $week, 'focus' => $week === $weeks ? 'Exam rehearsal' : 'Build up your '.$weakest, 'days' => $schedule];
        }

        return [
            'summary' => "Your plan targets band {$user->target_band} and gives extra time to {$weakest}, your weakest module so far.",
            'weeks' => $plan,
            'tips' => [
                'Practise speaking out loud every day, even for 10 minutes.',
                'Read the feedback after every attempt and repeat the same task type until the mistake disappears.',
                'Time yourself strictly for writing: 20 minutes for Task 1, 40 for Task 2.',
                'Listen to English podcasts or news daily and note new words.',
            ],
        ];
    }
}
