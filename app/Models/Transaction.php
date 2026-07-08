<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model {
    protected $table = 'transactions';
    protected $primaryKey = 'transaction_id';
    public const UPDATED_AT = null;
    protected $fillable = ['invoice_id', 'payment_method', 'amount_paid', 'payment_status', 'approved_by', 'is_platform_fee_deducted', 'deduction_scheduled_date'];

    public function invoice() {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function approvedBy() {
        return $this->belongsTo(User::class, 'approved_by', 'user_id');
    }
}
