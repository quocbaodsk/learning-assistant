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
    Schema::create('learning_weeks', function (Blueprint $table) {
      $table->id();
      $table->foreignId('learning_profile_id')->constrained()->onDelete('cascade');
      $table->text('summary'); // Ex: "A 22-year-old student..."
      $table->text('notes')->nullable(); // AI note khuyến nghị
      $table->date('start_date'); // đầu tuần
      $table->boolean('is_active')->default(true);
      $table->json('feedback')->nullable(); // phản hồi của AI
      $table->integer('user_id')->unsigned();
      $table->timestamps();
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::dropIfExists('learning_weeks');
  }
};
