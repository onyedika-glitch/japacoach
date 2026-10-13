<?php

namespace App\Console\Commands;

use App\Models\PracticeTest;
use App\Services\Content\ContentImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('content:import {--path= : Folder containing the seed JSON files}')]
#[Description('Import the bundled starter speaking, writing, reading and listening content')]
class ImportContent extends Command
{
    public function handle(ContentImporter $importer): int
    {
        $path = rtrim($this->option('path') ?: database_path('seeders/content'), '/');

        $speaking = $this->readJson("{$path}/speaking_questions.json");
        $writing = $this->readJson("{$path}/writing_tasks.json");
        $this->info('Speaking questions added: '.$importer->importSpeaking($speaking));
        $this->info('Writing tasks added: '.$importer->importWriting($writing));

        foreach (['reading' => 'reading_tests.json', 'listening' => 'listening_tests.json'] as $module => $file) {
            foreach ($this->readJson("{$path}/{$file}") as $test) {
                if (PracticeTest::where('module', $module)->where('title', $test['title'] ?? '')->exists()) {
                    continue;
                }

                try {
                    $created = $importer->importPracticeTest($module, $test);
                    $this->info("Imported {$module} test: {$created->title} ({$created->questions()->count()} questions)");
                } catch (InvalidArgumentException $exception) {
                    $this->error("Skipped {$module} test: {$exception->getMessage()}");
                }
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function readJson(string $file): array
    {
        if (! is_file($file)) {
            $this->warn("Missing {$file}");

            return [];
        }

        return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }
}
