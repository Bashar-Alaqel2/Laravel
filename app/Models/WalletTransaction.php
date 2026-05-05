<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class WalletTransaction extends Model {
    protected $table = 'wallet_transactions';
    protected $primaryKey = 'wallet_tx_id';
    public const UPDATED_AT = null;
    protected $fillable = ['wallet_id', 'transaction_type', 'amount', 'reference_id', 'description'];

    public function wallet() {
        return $this->belongsTo(Wallet::class, 'wallet_id', 'wallet_id');
    }
}