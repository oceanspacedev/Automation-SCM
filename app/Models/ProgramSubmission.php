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

    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
