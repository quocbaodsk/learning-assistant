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
        Schema::create('learning_task_exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_task_id')->constrained()->onDelete('cascade');
            $table->string('exercise');
            $table->text('instructions')->nullable();
            $table->text('answer')->nullable(); // Đáp án đúng
            $table->enum('type', ['written', 'multiple_choice'])->default('written'); // Kiểu câu hỏi
            $table->json('options')->nullable(); // Cho câu hỏi trắc nghiệm
            $table->text('user_answer')->nullable(); // Lưu câu trả lời của người học
            $table->boolean('is_submitted')->default(false); // Đánh dấu đã trả lời
            $table->tinyInteger('difficulty')->default(1);
            $table->integer('score')->default(1);

            $table->dateTime('end_time')->nullable(); // Thời gian kết thúc
            $table->dateTime('start_time')->nullable(); // Thời gian bắt đầu

            $table->integer('user_score')->default(0); // Điểm của người học
            $table->text('ai_feedback')->nullable(); // Phản hồi từ AI
            $table->boolean('is_correct')->default(false); // Đánh dấu đã trả lời đúng
            $table->text('ai_answer')->nullable(); // Đáp án AI
            $table->text('ai_evaluation')->nullable(); // Đánh giá của AI
            $table->text('ai_explanation')->nullable(); // Giải thích của AI

            $table->integer('user_id')->unsigned();


            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('learning_task_exercises');
    }
};
