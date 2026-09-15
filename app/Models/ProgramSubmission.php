<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProgramSubmission extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'raw_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
