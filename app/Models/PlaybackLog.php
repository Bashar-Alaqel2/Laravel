<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class PlaybackLog extends Model {
    protected $table = 'playback_logs';
    protected $primaryKey = 'log_id';
    public $timestamps = false;
    protected $fillable = ['ad_id', 'screen_id', 'played_at'];

    public function advertisement() {
        return $this->belongsTo(Advertisement::class, 'ad_id', 'ad_id');
    }

    public function screen() {
        return $this->belongsTo(Screen::class, 'screen_id', 'screen_id');
    }
}
