<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'device_id', 'fcm_token', 'platform', 'last_synced_at'];

    protected $casts = ['last_synced_at' => 'datetime'];
}
