<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FrequencyPackage extends Model
{
    protected $table = 'frequency_packages';
    protected $fillable = ['name', 'display_interval', 'price_multiplier'];
}
