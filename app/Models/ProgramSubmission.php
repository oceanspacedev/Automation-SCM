<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use App\Models\Concerns\BelongsToBrand;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgramSubmission extends Model
{
    use BelongsToArea, BelongsToBrand, SoftDeletes;
    use BelongsToCompany;

    public const STATUS_PURCHASE_OPTIONS = [
        'BELUM BISA POTONG',
        'BISA DI POTONG',
        'SUDAH POTONG',
        'DONE TRANSFER',
    ];

    public const KETERANGAN_OPTIONS = [
        'LEBIH DARI 30 HARI',
        'KURANG DARI 30 HARI',
    ];

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'incentive' => 'float',
        'dpp' => 'float',
        'dpp_lain' => 'float',
        'ppn' => 'float',
        'nilai_pph' => 'float',
        'net_pay' => 'float',
        'cek_pajak_tarif_pph' => 'float',
        'selisih' => 'float',
        'doc_validation' => 'array',
        'is_manual_edit' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Reconciliation history logs for this submission.
     */
    public function reconciliationLogs(): HasMany
    {
        return $this->hasMany(ProgramReconciliationLog::class, 'program_submission_id')->orderByDesc('id');
    }

    /**
     * Get effective WhatsApp recipient phone, defaulting to testing number if not set.
     */
    public function getEffectiveWhatsappAttribute(): string
    {
        return $this->whatsapp ?: config('services.wag.ar_phone', '081224290502');
    }

    /**
     * Lama Pending is the difference in calendar days: Today() - Tgl Share CN.
     * Equivalent to Excel formula: =DAYS(TODAY(), [Tgl Share CN]).
     */
    public function getLamaPendingAttribute($value): ?string
    {
        if (filled($this->tgl_share_cn)) {
            $days = DataProgram::daysSince($this->tgl_share_cn);
            if ($days !== null) {
                return (string) $days;
            }
        }

        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Keterangan aging text (LEBIH DARI 30 HARI / KURANG DARI 30 HARI).
     * Kept automatically in sync when empty or set to default aging status.
     */
    public function getKeteranganAttribute($value): ?string
    {
        if (empty($value) || in_array($value, ['LEBIH DARI 30 HARI', 'KURANG DARI 30 HARI'], true)) {
            if (filled($this->tgl_share_cn)) {
                $days = DataProgram::daysSince($this->tgl_share_cn);
                if ($days !== null) {
                    return DataProgram::agingKeterangan($days);
                }
            }
        }

        return $value;
    }
}
