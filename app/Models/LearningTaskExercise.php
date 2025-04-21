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
        'difficulty',
        'score',
        'is_done',
        'is_correct',
        'user_answer',

        'user_score',
        'ai_feedback',
        'ai_answer',
        'ai_evaluation',
        'ai_explanation',


    ];

    protected $casts = [
        'score'      => 'integer',
        'difficulty' => 'integer',
    ];

    protected $hidden = [
        '',
    ];

    public function task()
    {
        return $this->belongsTo(LearningTask::class, 'learning_task_id');
    }

}
