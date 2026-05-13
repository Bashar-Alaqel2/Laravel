<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialLedger extends Model
{
    protected $table = 'financial_ledgers';
    protected $primaryKey = 'ledger_id';

    protected $fillable = [
        'advertisement_id',
        'screen_id',
        'user_id',
        'transaction_type',
        'amount',
        'payment_method',
        'reference_number',
        'status',
        'notes',
        'receipt_path'
    ];

    // --- Relationships ---

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function advertisement()
    {
        return $this->belongsTo(Advertisement::class, 'advertisement_id', 'ad_id');
    }

    public function screen()
    {
        return $this->belongsTo(Screen::class, 'screen_id', 'screen_id');
    }
}
