<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataProgram extends Model
{
    use BelongsToArea, BelongsToBrand;

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
}
