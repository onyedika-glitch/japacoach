<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the starter practice content bundled in database/seeders/content.
     */
    public function run(): void
    {
        Artisan::call('content:import', [], $this->command?->getOutput());
    }
}
