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
        Schema::create('writing_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('task_number');
            $table->string('test_type');
            $table->string('title');
            $table->text('prompt');
            $table->json('visual')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['task_number', 'test_type', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('writing_tasks');
    }
};
