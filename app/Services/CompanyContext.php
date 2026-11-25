<?php

namespace App\Services;

use Closure;

class CompanyContext
{
    private int $companyId = 1;

    public function id(): int
    {
        return $this->companyId;
    }

    public function isLegacy(): bool
    {
        return $this->companyId === 1;
    }

    public function cacheKey(string $key): string
    {
        return $this->isLegacy() ? $key : "company:{$this->companyId}:{$key}";
    }

    public function run(int $companyId, Closure $callback): mixed
    {
        $previous = $this->companyId;
        $this->companyId = $companyId;
        $phones = [
            'services.wag.ar_phone' => config('services.wag.ar_phone'),
            'services.wag.telemarketing_phone' => config('services.wag.telemarketing_phone'),
        ];
        if (! $this->isLegacy()) {
            config(['services.wag.ar_phone' => '', 'services.wag.telemarketing_phone' => '']);
        }
        try {
            return $callback();
        } finally {
            $this->companyId = $previous;
            config($phones);
        }
    }
}
