<?php

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait BelongsToVendor
{
    public static function bootBelongsToVendor(): void
    {
        static::addGlobalScope('vendor', function (Builder $query) {
            $id = app(TenantContext::class)->id();
            $id ? $query->where($query->getModel()->qualifyColumn('vendor_id'), $id)
                : $query->whereRaw('1 = 0');
        });
        static::saving(function ($model) {
            $id = app(TenantContext::class)->id();
            if (! $id || ($model->exists && (int) $model->getOriginal('vendor_id') !== $id)) {
                throw ValidationException::withMessages(['vendor' => 'Invalid vendor context.']);
            }
            $model->vendor_id = $id;
        });
        static::deleting(function ($model) {
            if ((int) $model->vendor_id !== app(TenantContext::class)->id()) {
                throw ValidationException::withMessages(['vendor' => 'Invalid vendor context.']);
            }
        });
    }
}
