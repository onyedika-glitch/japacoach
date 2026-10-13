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
        Schema::create('practice_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practice_section_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('type');
            $table->text('prompt');
            $table->json('options')->nullable();
            $table->json('answers');
            $table->text('explanation')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('practice_questions');
    }
};
