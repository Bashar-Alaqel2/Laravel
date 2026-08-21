<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdPackage extends Model
{
    use HasFactory;

    protected $table = 'ad_packages';
    protected $primaryKey = 'package_id';

    protected $fillable = [
        'name_ar',
        'name_en',
        'interval_minutes',
        'price_multiplier',
        'is_active',
    ];

    protected $casts = [
        'interval_minutes' => 'integer',
        'price_multiplier' => 'float',
        'is_active' => 'boolean',
    ];
}
