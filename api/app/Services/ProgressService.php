<?php

namespace App\Services;

use App\Models\User;
use App\Services\Scoring\BandCalculator;
use Illuminate\Support\Collection;

/**
 * Collects a user's scored attempts across all modules for the dashboard.
 */
class ProgressService
{
    /**
     * @return array<string, float|null>
     */
    public function latestBands(User $user): array
    {
        $listening = $user->practiceAttempts()->where('module', 'listening')->where('status', 'submitted')->latest('submitted_at')->value('band');
        $reading = $user->practiceAttempts()->where('module', 'reading')->where('status', 'submitted')->latest('submitted_at')->value('band');

        return [
            'speaking' => $this->floatOrNull($user->speakingAttempts()->where('status', 'scored')->where('mode', 'full')->latest('scored_at')->value('overall_band')
                ?? $user->speakingAttempts()->where('status', 'scored')->latest('scored_at')->value('overall_band')),
            'writing' => $this->floatOrNull($user->writingAttempts()->where('status', 'scored')->latest('scored_at')->value('overall_band')),
            'reading' => $this->floatOrNull($reading),
            'listening' => $this->floatOrNull($listening),
        ];
    }

    /**
     * @param  array<string, float|null>  $latest
     */
    public function overall(array $latest): ?float
    {
        if (in_array(null, $latest, true)) {
            return null;
        }

        return BandCalculator::overall($latest['listening'], $latest['reading'], $latest['writing'], $latest['speaking']);
    }

    /**
     * Band history for charts, oldest first.
     *
     * @return Collection<int, array{date: string, module: string, band: float}>
     */
    public function history(User $user, int $limit = 60): Collection
    {
        $speaking = $user->speakingAttempts()->where('status', 'scored')->latest('scored_at')->limit($limit)->get(['scored_at', 'overall_band'])
            ->map(fn ($a): array => ['date' => $a->scored_at->toDateString(), 'module' => 'speaking', 'band' => $a->overall_band]);
        $writing = $user->writingAttempts()->where('status', 'scored')->latest('scored_at')->limit($limit)->get(['scored_at', 'overall_band'])
            ->map(fn ($a): array => ['date' => $a->scored_at->toDateString(), 'module' => 'writing', 'band' => $a->overall_band]);
        $practice = $user->practiceAttempts()->where('status', 'submitted')->latest('submitted_at')->limit($limit * 2)->get(['submitted_at', 'module', 'band'])
            ->map(fn ($a): array => ['date' => $a->submitted_at->toDateString(), 'module' => $a->module, 'band' => $a->band]);

        return $speaking->concat($writing)->concat($practice)->sortBy('date')->values();
    }

    /**
     * Most recent attempts across all modules.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function recent(User $user, int $limit = 8): Collection
    {
        $speaking = $user->speakingAttempts()->latest()->limit($limit)->get()
            ->map(fn ($a): array => [
                'id' => $a->id, 'module' => 'speaking',
                'title' => $a->mode === 'full' ? 'Full speaking test' : 'Speaking drill: '.str_replace('part', 'Part ', $a->mode),
                'band' => $a->overall_band, 'status' => $a->status, 'created_at' => $a->created_at->toIso8601String(),
            ]);
        $writing = $user->writingAttempts()->with('task:id,task_number,title')->latest()->limit($limit)->get()
            ->map(fn ($a): array => [
                'id' => $a->id, 'module' => 'writing', 'title' => "Task {$a->task->task_number}: {$a->task->title}",
                'band' => $a->overall_band, 'status' => $a->status, 'created_at' => $a->created_at->toIso8601String(),
            ]);
        $practice = $user->practiceAttempts()->with('test:id,title')->latest()->limit($limit)->get()
            ->map(fn ($a): array => [
                'id' => $a->id, 'module' => $a->module, 'title' => $a->test->title,
                'band' => $a->band, 'status' => $a->status, 'created_at' => $a->created_at->toIso8601String(),
            ]);

        return $speaking->concat($writing)->concat($practice)->sortByDesc('created_at')->take($limit)->values();
    }

    private function floatOrNull(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }
}
