<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SportProgressSnapshot extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'date', 'sessions_planned', 'sessions_completed', 'sport_minutes'];

    protected $casts = ['date' => 'date'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
