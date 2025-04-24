<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LearningTaskExercise extends Model
{
  protected $fillable = [
    'learning_task_id',
    'exercise',
    'instructions',
    'answer',
    'type',
    'options',
    'user_answer',
    'is_submitted',
    'difficulty',
    'score',
    'end_time',
    'start_time',
    'user_score',
    'ai_feedback',
    'is_correct',
    'ai_answer',
    'ai_evaluation',
    'ai_explanation',
    'user_id',
  ];

  protected $casts = [
    'end_time'   => 'datetime',
    'start_time' => 'datetime',

    'score'      => 'integer',
    'difficulty' => 'integer',

    'options'    => 'array',
  ];

  protected $appends = [
    'duration',
  ];

  protected $hidden = [
    'answer',
    'ai_answer',
    'instructions',
    'ai_evaluation',
    // 'ai_explanation',
  ];

  public function task()
  {
    return $this->belongsTo(LearningTask::class, 'learning_task_id');
  }

  // tính thời gian hoàn thành
  public function getDurationAttribute()
  {
    if ($this->is_sumitted && $this->end_time && $this->start_time) {
      return $this->end_time->diffInMinutes($this->start_time);
    }

    return 0;
  }

}
