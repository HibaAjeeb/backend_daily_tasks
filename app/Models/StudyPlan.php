<?php

namespace App\Models;

use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudyPlan extends Model
{
    use HasFactory, SyncableModel;

    protected $fillable = [
        'id',
        'user_id',
        'subject_name',
        'goal',
        'start_date',
        'end_date',
        'sync_status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    protected static function routeNotFoundCode(): string
    {
        return 'STUDY_PLAN_NOT_FOUND';
    }

    protected static function routeNotFoundMessage(): string
    {
        return 'The requested study plan was not found.';
    }

    public function tasks()
    {
        return $this->hasMany(Task::class, 'parent_plan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
