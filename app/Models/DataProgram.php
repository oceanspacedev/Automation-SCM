<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DataProgram extends Model
{
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
}
