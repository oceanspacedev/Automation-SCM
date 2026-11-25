<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/** Cache entries follow the company even inside long-lived queue workers. */
class CompanyCache
{
    /** @param array<int, mixed> $arguments */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $arguments[0] = app(CompanyContext::class)->cacheKey($arguments[0]);

        return Cache::$method(...$arguments);
    }
}
