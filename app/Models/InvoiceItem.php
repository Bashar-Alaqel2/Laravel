<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model {
    protected $table = 'invoice_items';
    protected $primaryKey = 'item_id';
    public $timestamps = false;
    protected $fillable = ['invoice_id', 'ad_id', 'item_price'];

    public function invoice() {
        return $this->belongsTo(Invoice::class, 'invoice_id', 'invoice_id');
    }

    public function advertisement() {
        return $this->belongsTo(Advertisement::class, 'ad_id', 'ad_id');
    }
}