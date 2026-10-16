<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use App\Models\Concerns\BelongsToBrand;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Draft invoice milik sebuah area; Data Program-nya dikirim ke spreadsheet area tersebut.
 */
class Draft extends Model
{
    use BelongsToArea, BelongsToBrand, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'real_qty' => 'decimal:2',
        'support_amount' => 'decimal:2',
        'dpp' => 'decimal:2',
        'dpp_lain' => 'decimal:2',
        'ppn' => 'decimal:2',
        'pph' => 'decimal:2',
        'netpay' => 'decimal:2',
        'validation_notes' => 'array',
    ];

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class)->withoutGlobalScopes(['area', 'brand']);
    }

    public function dataProgram(): HasOne
    {
        return $this->hasOne(DataProgram::class, 'draft_id')->withoutGlobalScopes(['area', 'brand']);
    }
}
