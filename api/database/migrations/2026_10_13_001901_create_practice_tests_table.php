<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('practice_tests', function (Blueprint $table) {
            $table->id();
            $table->string('module');
            $table->string('test_type')->nullable();
            $table->string('title');
            $table->unsignedSmallInteger('duration_minutes');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['module', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_tests');
    }
};
