<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncConflict extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['id', 'user_id', 'device_id', 'entity', 'entity_id', 'client_data', 'server_data', 'status', 'resolution', 'detected_at', 'resolved_at'];

    protected $casts = ['client_data' => 'array', 'server_data' => 'array', 'detected_at' => 'datetime', 'resolved_at' => 'datetime'];
}
