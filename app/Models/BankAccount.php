<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    protected $table = 'bank_accounts';
    protected $primaryKey = 'account_id';

    protected $fillable = [
        'user_id',
        'bank_name',
        'account_name',
        'account_number',
        'iban',
        'swift_code',
        'branch'
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
