<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DurationDiscount extends Model
{
    use HasFactory;

    protected $primaryKey = 'duration_discount_id';
    
    protected $fillable = [
        'name',
        'min_days',
        'discount_percentage',
        'is_active'
    ];
}
