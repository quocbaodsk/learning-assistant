<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LearningTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_week_id',
        'day',
        'task',
        'duration',
        'resource',
        'type',
        'focus',
        'is_done',
    ];

    protected $casts = [
        'is_done' => 'boolean',
    ];

    public function week()
    {
        return $this->belongsTo(LearningWeek::class, 'learning_week_id');
    }

    public function exercises()
    {
        return $this->hasMany(LearningTaskExercise::class);
    }

}
