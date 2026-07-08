<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdScreen extends Model {
    protected $table = 'ad_screens';
    protected $primaryKey = 'ad_screen_id';
    public $timestamps = false;
    protected $fillable = ['ad_id', 'screen_id', 'price'];

    public function advertisement() {
        return $this->belongsTo(Advertisement::class, 'ad_id', 'ad_id');
    }

    public function screen() {
        return $this->belongsTo(Screen::class, 'screen_id', 'screen_id');
    }
}
