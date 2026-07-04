<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $table = 'payment_methods';
    protected $primaryKey = 'method_id';
    
    protected $fillable = ['name', 'account_details', 'is_active', 'stripe_publishable_key', 'stripe_secret_key'];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
