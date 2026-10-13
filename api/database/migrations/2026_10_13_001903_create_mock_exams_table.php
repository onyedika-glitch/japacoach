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
        Schema::create('mock_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('test_type');
            $table->string('status')->default('in_progress');
            $table->foreignId('listening_test_id')->nullable()->constrained('practice_tests')->nullOnDelete();
            $table->foreignId('reading_test_id')->nullable()->constrained('practice_tests')->nullOnDelete();
            $table->foreignId('writing_task1_id')->nullable()->constrained('writing_tasks')->nullOnDelete();
            $table->foreignId('writing_task2_id')->nullable()->constrained('writing_tasks')->nullOnDelete();
            $table->json('bands')->nullable();
            $table->decimal('overall_band', 2, 1)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_exams');
    }
};
