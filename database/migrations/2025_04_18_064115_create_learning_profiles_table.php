<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('learning_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->json('interests')->nullable();
            $table->string('primary_skill');
            $table->unsignedTinyInteger('skill_level'); // 0–100
            $table->json('secondary_skills')->nullable();
            $table->text('goals')->nullable();
            $table->string('learning_style')->nullable(); // Visual / Auditory / ...
            $table->string('daily_learning_time')->nullable(); // e.g., "1 hour"
            $table->json('preferred_resources')->nullable(); // videos, articles, ...
            $table->text('custom_ai_prompt')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_profiles');
    }
};
