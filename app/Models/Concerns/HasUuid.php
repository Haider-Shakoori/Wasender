<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasUuid
{
    protected static function bootHasUuid(): void
    {
        static::creating(function (object $model): void {
            if (blank($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }
}
