<?php

namespace App\Models;

use App\Models\Concerns\BelongsToArea;
use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Region (mis. BIG BANDUNG) milik sebuah area (Jawa Barat/Jawa Tengah).
 */
class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use BelongsToArea, HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
