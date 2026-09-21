<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyProgressSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'date', 'tasks_planned', 'tasks_completed', 'study_minutes'];

    protected $casts = ['date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
