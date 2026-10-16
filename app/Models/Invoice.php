<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice dari sebuah draft; area (Jawa Barat/Jawa Tengah) dan brandnya mengikuti draft tersebut.
 */
class Invoice extends Model
{
    use BelongsToArea, BelongsToBrand, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'support_amount' => 'decimal:2',
        'dpp' => 'decimal:2',
        'dpp_lain' => 'decimal:2',
        'ppn' => 'decimal:2',
        'pph' => 'decimal:2',
        'netpay' => 'decimal:2',
        'email_sent_at' => 'datetime',
        'whatsapp_sent_at' => 'datetime',
    ];

    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class)->withoutGlobalScopes(['area', 'brand']);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(WhatsAppLog::class);
    }

    /**
     * Display name of the invoice's brand (e.g. "Realme").
     */
    public function brandName(): string
    {
        return Brand::nameFor($this->brand);
    }

    /**
     * Link where the dealer uploads this program's documents (CN, Agreement, Faktur Pajak).
     */
    public function documentUploadUrl(?string $baseUrl = null): string
    {
        return Brand::documentUploadUrl($this->brand, $this->areaEnum(), $baseUrl ?? url('/'));
    }

    public function getTerbilangAttribute(): string
    {
        return self::numberToWords((int) round($this->netpay)).' Rupiah';
    }

    public static function numberToWords(int|float $number): string
    {
        $number = (int) abs($number);
        $words = ['', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas'];

        if ($number < 12) {
            $result = $words[$number];
        } elseif ($number < 20) {
            $result = self::numberToWords($number - 10).' Belas';
        } elseif ($number < 100) {
            $result = self::numberToWords(intdiv($number, 10)).' Puluh '.self::numberToWords($number % 10);
        } elseif ($number < 200) {
            $result = 'Seratus '.self::numberToWords($number - 100);
        } elseif ($number < 1000) {
            $result = self::numberToWords(intdiv($number, 100)).' Ratus '.self::numberToWords($number % 100);
        } elseif ($number < 2000) {
            $result = 'Seribu '.self::numberToWords($number - 1000);
        } elseif ($number < 1000000) {
            $result = self::numberToWords(intdiv($number, 1000)).' Ribu '.self::numberToWords($number % 1000);
        } elseif ($number < 1000000000) {
            $result = self::numberToWords(intdiv($number, 1000000)).' Juta '.self::numberToWords($number % 1000000);
        } elseif ($number < 1000000000000) {
            $result = self::numberToWords(intdiv($number, 1000000000)).' Miliar '.self::numberToWords($number % 1000000000);
        } else {
            $result = self::numberToWords(intdiv($number, 1000000000000)).' Triliun '.self::numberToWords($number % 1000000000000);
        }

        return trim(preg_replace('/\s+/', ' ', $result));
    }
}
