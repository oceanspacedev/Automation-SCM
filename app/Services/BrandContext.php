<?php

namespace App\Services;

use App\Models\Brand;
use Closure;

/**
 * Holds the brand (Realme, Oppo, ...) the current request works in.
 * When a brand is set, brand-aware models (Draft, Invoice, DataProgram, ...) are automatically scoped to it.
 */
class BrandContext
{
    protected ?string $brand = null;

    /**
     * The explicitly selected brand slug, or null when no brand scoping applies (e.g. console, public links).
     */
    public function current(): ?string
    {
        return $this->brand;
    }

    /**
     * The selected brand slug, falling back to the default brand (Realme).
     */
    public function resolve(): string
    {
        return $this->brand ?? Brand::DEFAULT_SLUG;
    }

    /**
     * Whether the resolved brand is the default brand, which keeps the original (legacy) settings & links.
     */
    public function isDefault(): bool
    {
        return $this->resolve() === Brand::DEFAULT_SLUG;
    }

    public function set(?string $brand): void
    {
        $this->brand = $brand;
    }

    /**
     * Run a callback with the given brand selected, restoring the previous brand afterwards.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(string $brand, Closure $callback): mixed
    {
        $previous = $this->brand;
        $this->brand = $brand;

        try {
            return $callback();
        } finally {
            $this->brand = $previous;
        }
    }
}
