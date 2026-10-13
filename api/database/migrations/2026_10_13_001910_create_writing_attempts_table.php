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
        Schema::create('writing_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('writing_task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_exam_id')->nullable()->constrained()->nullOnDelete();
            $table->text('essay');
            $table->unsignedInteger('word_count');
            $table->unsignedInteger('time_spent_seconds')->nullable();
            $table->string('status')->default('processing');
            $table->json('scores')->nullable();
            $table->json('feedback')->nullable();
            $table->decimal('overall_band', 2, 1)->nullable();
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('writing_attempts');
    }
};
