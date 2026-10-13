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
        Schema::create('speaking_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('speaking_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('speaking_question_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('part');
            $table->unsignedTinyInteger('position');
            $table->string('audio_path')->nullable();
            $table->decimal('duration_seconds', 6, 1)->nullable();
            $table->string('status')->default('pending');
            $table->text('transcript')->nullable();
            $table->json('words')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
            $table->unique(['speaking_attempt_id', 'speaking_question_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speaking_answers');
    }
};
