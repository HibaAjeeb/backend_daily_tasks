<?php

namespace App\Models;

use App\Models\Concerns\SyncableModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory, SyncableModel;

    protected $fillable = [
        'user_id',
        'name',
        'color_value',
        'icon',
        'sync_status',
    ];

    protected static function routeNotFoundCode(): string
    {
        return 'CATEGORY_NOT_FOUND';
    }

    protected static function routeNotFoundMessage(): string
    {
        return 'The requested category was not found.';
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
