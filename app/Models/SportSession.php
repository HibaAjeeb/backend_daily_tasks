<?php

namespace App\Models;

use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportSession extends Model
{
    use HasFactory, SyncableModel;

    protected $fillable = [
        'id',
        'user_id',
        'exercise_name',
        'date',
        'scheduled_time',
        'duration_minutes',
        'actual_duration_minutes',
        'is_completed',
        'completed_at',
        'sync_status',
    ];

    protected $casts = [
        'date' => 'date',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    protected static function routeNotFoundCode(): string
    {
        return 'SPORT_SESSION_NOT_FOUND';
    }

    protected static function routeNotFoundMessage(): string
    {
        return 'The requested sport session was not found.';
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
