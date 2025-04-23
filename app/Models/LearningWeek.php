<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LearningWeek extends Model
{
    use HasFactory;

    protected $fillable = [
        'learning_profile_id',
        'summary',
        'notes',
        'start_date',
        'is_active',
        'user_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'is_active'  => 'boolean',
    ];

    protected $appends = [
        'is_ready',
    ];

    public function profile()
    {
        return $this->belongsTo(LearningProfile::class, 'learning_profile_id');
    }

    public function tasks()
    {
        return $this->hasMany(LearningTask::class);
    }

    public function getIsReadyAttribute()
    {
        $status = LearningTask::where('learning_week_id', $this->id)
            ->where('is_done', false)
            ->exists();

        if ($status !== $this->is_active) {
            $this->update(['is_active' => $status]);
        }

        return !$status;
    }

}
