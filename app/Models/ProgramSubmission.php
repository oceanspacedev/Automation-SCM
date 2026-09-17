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
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
