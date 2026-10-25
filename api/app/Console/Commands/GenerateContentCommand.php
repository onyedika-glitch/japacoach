<?php

namespace App\Console\Commands;

use App\Jobs\GenerateContent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('content:generate {type : speaking|writing|reading|listening} {--count=1} {--test-type=academic : academic|general (reading tests; for writing, general makes letters only)} {--sync : Run now instead of queueing}')]
#[Description('Generate new practice content with DeepSeek')]
class GenerateContentCommand extends Command
{
    public function handle(): int
    {
        $type = (string) $this->argument('type');

        if (! in_array($type, ['speaking', 'writing', 'reading', 'listening'], true)) {
            $this->error('Type must be speaking, writing, reading or listening.');

            return self::INVALID;
        }

        $count = max(1, (int) $this->option('count'));

        for ($i = 1; $i <= $count; $i++) {
            $job = new GenerateContent($type, (string) $this->option('test-type'));

            if ($this->option('sync')) {
                $this->info("Generating {$type} batch {$i}/{$count}…");
                dispatch_sync($job);
            } else {
                dispatch($job);
            }
        }

        $this->info($this->option('sync') ? 'Done.' : "Queued {$count} {$type} generation job(s).");

        return self::SUCCESS;
    }
}
