<?php

namespace App\Models\Concerns;

use App\Scopes\OwnedByUserScope;

trait OwnedModel
{
    protected static function bootOwnedModel(): void
    {
        static::addGlobalScope(new OwnedByUserScope);
        static::creating(function ($model): void {
            if (! $model->user_id && auth()->check()) {
                $model->user_id = auth()->id();
            }
        });
    }
}
