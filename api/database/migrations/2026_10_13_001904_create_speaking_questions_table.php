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
        Schema::create('speaking_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('part');
            $table->string('topic');
            $table->text('prompt');
            $table->json('cue_points')->nullable();
            $table->string('audio_path')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['part', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speaking_questions');
    }
};
