<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Wallet extends Model {
    protected $table = 'wallets';
    protected $primaryKey = 'wallet_id';
    public $timestamps = false;
    protected $fillable = ['user_id', 'available_balance'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function transactions() {
        return $this->hasMany(WalletTransaction::class, 'wallet_id', 'wallet_id');
    }
}
