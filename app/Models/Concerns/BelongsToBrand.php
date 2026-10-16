<?php

namespace App\Models\Concerns;

use App\Services\BrandContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes a model to the brand selected in BrandContext and stamps new records with that brand.
 *
 * @property string $brand
 */
trait BelongsToBrand
{
    public static function bootBelongsToBrand(): void
    {
        static::addGlobalScope('brand', function (Builder $query): void {
            if ($brand = app(BrandContext::class)->current()) {
                $query->where($query->getModel()->qualifyColumn('brand'), $brand);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->brand)) {
                $model->brand = $model->brandForNewRecord();
            }
        });
    }

    /**
     * The brand a new record belongs to when none is given (the selected brand, or Realme).
     */
    protected function brandForNewRecord(): string
    {
        return app(BrandContext::class)->resolve();
    }
}
