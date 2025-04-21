<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LearningProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'primary_skill',
        'skill_level',
        'secondary_skills',
        'goals',
        'interests',
        'learning_style',
        'daily_learning_time',
        'preferred_resources',
        'custom_ai_prompt',
    ];

    protected $casts = [
        'interests'           => 'array',
        'secondary_skills'    => 'array',
        'preferred_resources' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function weeks()
    {
        return $this->hasMany(LearningWeek::class);
    }
}
