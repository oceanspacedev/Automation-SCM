<?php

namespace App;

/**
 * Wilayah operasional yang memiliki Form Program & Data Program (dan Google Spreadsheet) masing-masing.
 */
enum Area: string
{
    case Jabar = 'jabar';
    case Jateng = 'jateng';

    /**
     * Default area for existing data, old links, and old Apps Script webhooks.
     */
    public static function default(): self
    {
        return self::Jabar;
    }

    /**
     * Resolve an area from user input (case-insensitive), or null when invalid/empty.
     */
    public static function fromInput(mixed $value): ?self
    {
        return is_string($value) ? self::tryFrom(strtolower(trim($value))) : null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Jabar => 'Jawa Barat',
            self::Jateng => 'Jawa Tengah',
        };
    }

    /**
     * Cache key for an area-specific setting. Jabar keeps the original (legacy) key.
     */
    public function settingKey(string $baseKey): string
    {
        return $this === self::default() ? $baseKey : "{$baseKey}_{$this->value}";
    }
}
