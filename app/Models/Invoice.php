<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Builder;
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

    protected $appends = ['is_uploaded_to_form'];

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
     * Store record from Master Dealer.
     */
    public function dealer(): ?Dealer
    {
        return Dealer::findByIdReal($this->draft?->kode_bt, $this->dealer_name)
            ?? Dealer::findByIdReal($this->dealer_code ?: $this->draft?->dealer_code, $this->dealer_name);
    }

    /**
     * Check whether this invoice has had its claim documents uploaded to Form Program.
     */
    public function isUploadedToForm(): bool
    {
        $kodeBt = $this->dealer_code ?: $this->draft?->kode_bt;
        if (! empty($kodeBt)) {
            $hasSubmission = ProgramSubmission::withoutGlobalScopes(['area', 'brand'])
                ->where('id_real', $kodeBt)
                ->where('program_name', $this->program_name)
                ->where(function ($q) {
                    $q->whereNotNull('credit_note_url')->orWhereNotNull('agreement_url');
                })
                ->exists();

            if ($hasSubmission) {
                return true;
            }
        }

        $dp = DataProgram::withoutGlobalScopes(['area', 'brand'])
            ->where(function ($q) {
                $q->where('cn', $this->invoice_number);
                if ($this->draft_id) {
                    $q->orWhere('draft_id', $this->draft_id);
                }
            })
            ->first();

        if ($dp) {
            if (! empty($dp->cn) && filter_var($dp->cn, FILTER_VALIDATE_URL)) {
                return true;
            }
            if (! empty($dp->agrement) && filter_var($dp->agrement, FILTER_VALIDATE_URL)) {
                return true;
            }
            if (in_array(strtoupper(trim((string) $dp->cek_dokumen)), ['LENGKAP', 'TIDAK LENGKAP', 'TTD / CAP SALAH', 'BELUM VALIDASI'], true)) {
                return true;
            }
        }

        return false;
    }

    public function getIsUploadedToFormAttribute(): bool
    {
        return array_key_exists('is_uploaded_to_form', $this->attributes)
            ? (bool) $this->attributes['is_uploaded_to_form']
            : $this->isUploadedToForm();
    }

    /**
     * Scope query to invoices that have their claim documents uploaded to Form Program.
     */
    public function scopeWhereUploadedToForm(Builder $query): Builder
    {
        return $query->where(function (Builder $outer) {
            $outer->whereExists(function ($sub) {
                $sub->selectRaw(1)->from('program_submissions')
                    ->whereColumn('program_submissions.id_real', 'invoices.dealer_code')
                    ->whereColumn('program_submissions.program_name', 'invoices.program_name')
                    ->where(fn ($q) => $q->whereNotNull('program_submissions.credit_note_url')->orWhereNotNull('program_submissions.agreement_url'));
            })->orWhereExists(function ($dp) {
                $dp->selectRaw(1)->from('data_programs')
                    ->where(function ($q) {
                        $q->whereColumn('data_programs.cn', 'invoices.invoice_number')
                            ->orWhere(function ($dq) {
                                $dq->whereNotNull('invoices.draft_id')
                                    ->whereColumn('data_programs.draft_id', 'invoices.draft_id');
                            });
                    })
                    ->where(function ($q) {
                        $q->whereIn('data_programs.cek_dokumen', ['LENGKAP', 'TIDAK LENGKAP', 'TTD / CAP SALAH', 'BELUM VALIDASI'])
                            ->orWhere('data_programs.cn', 'like', 'http%')
                            ->orWhere('data_programs.agrement', 'like', 'http%');
                    });
            });
        });
    }

    /**
     * Scope query to invoices that have NOT had their claim documents uploaded to Form Program.
     */
    public function scopeWhereNotUploadedToForm(Builder $query): Builder
    {
        return $query->whereNot(function (Builder $outer) {
            $outer->whereExists(function ($sub) {
                $sub->selectRaw(1)->from('program_submissions')
                    ->whereColumn('program_submissions.id_real', 'invoices.dealer_code')
                    ->whereColumn('program_submissions.program_name', 'invoices.program_name')
                    ->where(fn ($q) => $q->whereNotNull('program_submissions.credit_note_url')->orWhereNotNull('program_submissions.agreement_url'));
            })->orWhereExists(function ($dp) {
                $dp->selectRaw(1)->from('data_programs')
                    ->where(function ($q) {
                        $q->whereColumn('data_programs.cn', 'invoices.invoice_number')
                            ->orWhere(function ($dq) {
                                $dq->whereNotNull('invoices.draft_id')
                                    ->whereColumn('data_programs.draft_id', 'invoices.draft_id');
                            });
                    })
                    ->where(function ($q) {
                        $q->whereIn('data_programs.cek_dokumen', ['LENGKAP', 'TIDAK LENGKAP', 'TTD / CAP SALAH', 'BELUM VALIDASI'])
                            ->orWhere('data_programs.cn', 'like', 'http%')
                            ->orWhere('data_programs.agrement', 'like', 'http%');
                    });
            });
        });
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
    public function documentUploadUrl(): string
    {
        return Brand::documentUploadUrl($this->brand, $this->areaEnum());
    }

    public function getTerbilangAttribute(): string
    {
        return self::numberToWords((int) round($this->netpay)).' Rupiah';
    }

    /**
     * Nama DSA / NPS FL yang ditampilkan di invoice.
     * Mengambil dari kolom dsa invoice, fallback ke draft->dsa, atau ke sales_person di Master Dealer.
     */
    public function getDsaNameAttribute(): ?string
    {
        if (! empty($this->attributes['dsa'])) {
            return $this->attributes['dsa'];
        }

        if (! empty($this->draft?->dsa)) {
            return $this->draft->dsa;
        }

        $dealer = Dealer::where('kode_bt', $this->draft?->kode_bt ?? $this->dealer_code)
            ->orWhere('id_realme', $this->dealer_code)
            ->first();

        return $dealer?->sales_person;
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
