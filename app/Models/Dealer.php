<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Toko/dealer dari master CV TOP, dikenali dari kode BT ("ID Customer").
 * ID Realme ("ID GS") bisa kosong atau dipakai beberapa toko.
 */
class Dealer extends Model
{
    use BelongsToArea, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Normalize a kode BT: uppercase, trimmed, and "BTO…" (letter O) written as "BT0…" (zero),
     * e.g. "bto5768" → "BT05768".
     */
    public static function normalizeKodeBt(?string $kodeBt): ?string
    {
        $kodeBt = strtoupper(trim((string) $kodeBt));

        if ($kodeBt === '' || $kodeBt === '-') {
            return null;
        }

        return preg_replace('/^BTO(?=\d)/', 'BT0', $kodeBt);
    }

    /**
     * Find a store from the form's ID REALME: by kode BT first (unique), then by ID Realme,
     * which several stores may share, so the dealer name picks the right one.
     */
    public static function findByIdReal(?string $idReal, ?string $dealerName = null): ?self
    {
        $idReal = strtoupper(trim((string) $idReal));

        if ($idReal === '' || $idReal === '-') {
            return null;
        }

        $dealer = static::query()->whereIn('kode_bt', array_unique([static::normalizeKodeBt($idReal), $idReal]))->first();

        if ($dealer) {
            return $dealer;
        }

        $dealers = static::query()->where('id_realme', $idReal)->get();
        $dealerName = strtoupper(trim((string) $dealerName));

        return $dealers->first(fn (self $dealer) => strtoupper(trim((string) $dealer->dealer_name)) === $dealerName) ?? $dealers->first();
    }

    /**
     * PKP status from Jenis NPWP: BADAN & PRIBADI PKP must provide a Faktur Pajak, PRIBADI / NON / BADAN NON don't.
     * Null when the type is empty or unknown (e.g. BONUS), so the documents decide instead.
     */
    public function isPkp(): ?bool
    {
        $npwpType = strtoupper(trim((string) $this->npwp_type));

        return match (true) {
            str_contains($npwpType, 'NON') => false,
            str_contains($npwpType, 'PKP'), $npwpType === 'BADAN' => true,
            $npwpType === 'PRIBADI' => false,
            default => null,
        };
    }
}
