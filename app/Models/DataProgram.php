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
     * Draft origin if created or synced from Draft.
     */
    public function draft(): BelongsTo
    {
        return $this->belongsTo(Draft::class, 'draft_id')->withoutGlobalScopes(['area', 'brand']);
    }
}
