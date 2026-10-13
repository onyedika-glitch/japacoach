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
        Schema::create('practice_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('practice_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mock_exam_id')->nullable()->constrained()->nullOnDelete();
            $table->string('module');
            $table->string('status')->default('in_progress');
            $table->json('answers')->nullable();
            $table->unsignedSmallInteger('correct_count')->nullable();
            $table->unsignedSmallInteger('total_count')->nullable();
            $table->decimal('band', 2, 1)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'module', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_attempts');
    }
};
