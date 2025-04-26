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
    'feedback',
  ];

  protected $casts = [
    'feedback'   => 'array',
    'start_date' => 'date',
    'is_active'  => 'boolean',
  ];

  public function profile()
  {
    return $this->belongsTo(LearningProfile::class, 'learning_profile_id');
  }

  public function tasks()
  {
    return $this->hasMany(LearningTask::class);
  }

}
