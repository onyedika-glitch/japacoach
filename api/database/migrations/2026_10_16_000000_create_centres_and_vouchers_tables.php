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
        // A tutor's class or a prep centre. Students join with the join code; the owner sees their progress.
        Schema::create('centres', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('join_code', 16)->unique();
            $table->timestamps();
        });

        Schema::create('centre_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('centre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['centre_id', 'user_id']);
        });

        // Prepaid plan codes, sold in bulk to centres and redeemed by students on the website.
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('plan');
            $table->unsignedSmallInteger('days');
            $table->foreignId('centre_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('redeemed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('centre_user');
        Schema::dropIfExists('centres');
    }
};
