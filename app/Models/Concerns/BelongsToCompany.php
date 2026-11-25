<?php

namespace App\Models\Concerns;

use App\Services\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $query): void {
            $query->where($query->getModel()->qualifyColumn('company_id'), app(CompanyContext::class)->id());
        });

        static::creating(function (Model $model): void {
            $model->company_id = app(CompanyContext::class)->id();
        });

        static::updating(function (Model $model): void {
            $model->company_id = $model->getOriginal('company_id');
        });
    }
}
