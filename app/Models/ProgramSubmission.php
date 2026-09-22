<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramSubmission extends Model
{
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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
