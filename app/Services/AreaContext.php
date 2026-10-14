<?php

namespace App\Services;

use App\Area;
use Closure;

/**
 * Holds the area (Jabar/Jateng) the current request works in.
 * When an area is set, area-aware models (DataProgram, ProgramSubmission) are automatically scoped to it.
 */
class AreaContext
{
    protected ?Area $area = null;

    /**
     * The explicitly selected area, or null when no area scoping applies (e.g. console, public links).
     */
    public function current(): ?Area
    {
        return $this->area;
    }

    /**
     * The selected area, falling back to the default area.
     */
    public function resolve(): Area
    {
        return $this->area ?? Area::default();
    }

    public function set(?Area $area): void
    {
        $this->area = $area;
    }

    /**
     * Run a callback with the given area selected, restoring the previous area afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Area $area, Closure $callback): mixed
    {
        $previous = $this->area;
        $this->area = $area;

        try {
            return $callback();
        } finally {
            $this->area = $previous;
        }
    }
}
