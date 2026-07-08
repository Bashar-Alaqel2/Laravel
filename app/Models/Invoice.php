<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model {
    protected $table = 'invoices';
    protected $primaryKey = 'invoice_id';
    public $timestamps = false;
    protected $fillable = ['invoice_number', 'advertiser_id', 'total_amount', 'total_platform_fee', 'total_owner_share', 'status', 'issue_date'];

    public function advertiser() {
        return $this->belongsTo(User::class, 'advertiser_id', 'user_id');
    }

    public function items() {
        return $this->hasMany(InvoiceItem::class, 'invoice_id', 'invoice_id');
    }

    public function transactions() {
        return $this->hasMany(Transaction::class, 'invoice_id', 'invoice_id');
    }
}
