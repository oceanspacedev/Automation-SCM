<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class DataProgram extends Model
{
    use BelongsToArea, BelongsToBrand;

    /**
     * AR status after the dealer agreed (through telemarketing) to be paid by bank transfer instead of a cut: the SCM
     * team transfers, then the tax team checks, without AR.
     */
    public const TRANSFER_APPROVED = 'DEALER SETUJU (PROSES TRANSFER)';

    /**
     * Purchase & AR status of a claim the SCM team has transferred to the dealer.
     */
    public const DONE_TRANSFER = 'DONE TRANSFER';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'incentive' => 'float',
            'dpp' => 'float',
            'dpp_lain' => 'float',
            'ppn' => 'float',
            'nilai_pph' => 'float',
            'net_pay' => 'float',
            'selisih' => 'float',
            'telemarketing_notified_at' => 'datetime',
            'telemarketing_reminder_count' => 'integer',
            'tax_notified_at' => 'datetime',
            'tax_checked_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Reconciliation history logs for this data program.
     */
    public function reconciliationLogs(): HasMany
    {
        return $this->hasMany(ProgramReconciliationLog::class, 'data_program_id')->orderByDesc('id');
    }

    /**
     * Recorded steps of the approval flow (WhatsApp to telemarketing, the dealer's answer, WhatsApp to AR, the cut).
     */
    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ProgramApprovalLog::class, 'data_program_id')->withoutGlobalScopes(['area', 'brand'])->orderBy('id');
    }

    /**
     * Draft origin if created or synced from Draft.
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class, 'draft_id')->withoutGlobalScopes(['area', 'brand']);
    }

    /**
     * Whether the claim is paid by bank transfer (through the SCM team) rather than cut from the dealer's orders: the
     * statuses tell once the dealer chose, before that the T/F column ("TF").
     */
    public function isTransfer(): bool
    {
        $purchase = strtoupper(trim((string) $this->status_potong_purchase));
        $ar = strtoupper(trim((string) $this->status_potong_ar));

        // Yang sudah selesai menang: ditransfer, lalu dipotong; baru pilihan yang masih berjalan
        if (in_array(self::DONE_TRANSFER, [$purchase, $ar], true)) {
            return true;
        }

        if ($purchase === 'SUDAH POTONG' || in_array($ar, ['DEALER SETUJU (PROSES AR)', 'DONE', 'SUDAH POTONG'], true)) {
            return false;
        }

        return $ar === self::TRANSFER_APPROVED || strtoupper(trim((string) $this->tf_status)) === 'TF';
    }

    /**
     * Tgl Input is the day the No PO/SJ came in: today when the number is filled or changed (unless a Tgl Input is set
     * with it), and with $whenMissing also when a row with a number has no Tgl Input yet (e.g. the same number imported
     * again after the dates were moved to Tgl Share CN).
     */
    public function stampInputDate(bool $whenMissing = false): void
    {
        if (blank($this->no_po_sj)) {
            return;
        }

        $dateGiven = $this->isDirty('tgl_input') && filled($this->tgl_input);

        if (($this->isDirty('no_po_sj') && ! $dateGiven) || ($whenMissing && blank($this->tgl_input))) {
            $this->tgl_input = now()->toDateString();
        }
    }

    /**
     * Days since a date written as "2026-10-15" or "15/10/2026" (day first, as in the spreadsheet), or null when it
     * isn't such a date. Lama Pending counts from Tgl Share CN this way.
     */
    public static function daysSince(?string $date): ?int
    {
        $date = trim((string) $date);

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $date, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $date, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        return checkdate($month, $day, $year) ? max(0, (int) Carbon::create($year, $month, $day)->diffInDays(now())) : null;
    }

    /**
     * The aging Keterangan for a number of pending days.
     */
    public static function agingKeterangan(int $days): string
    {
        return $days > 30 ? 'LEBIH DARI 30 HARI' : 'KURANG DARI 30 HARI';
    }
}
