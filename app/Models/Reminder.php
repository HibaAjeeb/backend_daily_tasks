<?php

namespace App\Models;

use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory, SyncableModel;

    protected $fillable = [
        'id',
        'user_id',
        'task_id',
        'scheduled_for',
        'message',
        'repeat',
        'is_enabled',
        'is_dispatched',
        'dispatched_at',
        'sync_status',
    ];

    protected $casts = [
        'scheduled_for' => 'datetime',
        'is_enabled' => 'boolean',
        'is_dispatched' => 'boolean',
        'dispatched_at' => 'datetime',
    ];

    protected static function routeNotFoundCode(): string
    {
        return 'REMINDER_NOT_FOUND';
    }

    protected static function routeNotFoundMessage(): string
    {
        return 'The requested reminder was not found.';
    }

    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
