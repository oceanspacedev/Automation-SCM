<?php

namespace App\Models\Concerns;

use App\Area;
use App\Services\AreaContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Scopes a model to the area selected in AreaContext and stamps new records with that area.
 *
 * @property string $area
 */
trait BelongsToArea
{
    public static function bootBelongsToArea(): void
    {
        static::addGlobalScope('area', function (Builder $query): void {
            if ($area = app(AreaContext::class)->current()) {
                $query->where($query->getModel()->qualifyColumn('area'), $area->value);
            }
        });

        static::creating(function ($model): void {
            if (empty($model->area)) {
                $model->area = $model->areaForNewRecord()->value;
            }
        });
    }

    /**
     * The area a new record belongs to when none is given (the selected area, or Jawa Barat).
     */
    protected function areaForNewRecord(): Area
    {
        return app(AreaContext::class)->resolve();
    }

    /**
     * The model's area as an enum.
     */
    public function areaEnum(): Area
    {
        return Area::fromInput($this->area) ?? Area::default();
    }
}
