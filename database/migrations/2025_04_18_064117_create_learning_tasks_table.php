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
        Schema::create('learning_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_week_id')->constrained()->onDelete('cascade');
            $table->enum('day', ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']);
            $table->string('task');
            $table->string('duration'); // e.g., "30 minutes"
            $table->string('resource');
            $table->string('type'); // Video, Article, ...
            $table->string('focus'); // Primary Skill: X
            $table->boolean('is_done')->default(false); // ✅ người dùng tick hoàn thành
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_tasks');
    }
};
