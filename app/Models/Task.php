<?php

namespace App\Models;

use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    use HasFactory, SyncableModel;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'type',
        'due_date',
        'scheduled_time',
        'duration_minutes',
        'priority',
        'is_completed',
        'completed_at',
        'is_recurring',
        'recurrence_rule',
        'category_id',
        'parent_plan_id',
        'sync_status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_completed' => 'boolean',
            'is_recurring' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    protected static function routeNotFoundCode(): string
    {
        return 'TASK_NOT_FOUND';
    }

    protected static function routeNotFoundMessage(): string
    {
        return 'The requested task was not found.';
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function studyPlan()
    {
        return $this->belongsTo(StudyPlan::class, 'parent_plan_id');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }
}
