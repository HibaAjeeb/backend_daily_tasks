<?php

namespace App\Models\Concerns;

use App\Exceptions\ApiException;
use App\Scopes\OwnedByUserScope;
use Illuminate\Database\Eloquent\SoftDeletes;

trait SyncableModel
{
    use OwnedModel, SoftDeletes;

    public function initializeSyncableModel(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }

    protected static function bootSyncableModel(): void
    {
        static::creating(function ($model): void {
            $model->id ??= (string) str()->uuid();
            $model->sync_status ??= 'synced';
        });
    }

    public function resolveRouteBinding($value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        $model = static::withoutGlobalScope(OwnedByUserScope::class)
            ->where($field, $value)
            ->first();

        if (! $model) {
            throw new ApiException(static::routeNotFoundCode(), static::routeNotFoundMessage(), 404);
        }

        return $model;
    }

    protected static function routeNotFoundCode(): string
    {
        return 'NOT_FOUND';
    }

    protected static function routeNotFoundMessage(): string
    {
        return 'The requested resource was not found.';
    }
}
