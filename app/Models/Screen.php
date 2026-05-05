<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Screen extends Model {
    use SoftDeletes;
    protected $table = 'screens';
    protected $primaryKey = 'screen_id';
    public $timestamps = false;
    protected $fillable = ['owner_id', 'type_id', 'street_id', 'screen_name', 'status', 'linked_by', 'linked_at', 'disconnected_at'];

    public function owner() {
        return $this->belongsTo(User::class, 'owner_id', 'user_id');
    }

    public function linkedBy() {
        return $this->belongsTo(User::class, 'linked_by', 'user_id');
    }

    public function type() {
        return $this->belongsTo(ScreenType::class, 'type_id', 'type_id');
    }

    public function street() {
        return $this->belongsTo(Street::class, 'street_id', 'street_id');
    }

    public function advertisements() {
        return $this->belongsToMany(Advertisement::class, 'ad_screens', 'screen_id', 'ad_id')
                    ->withPivot('price');
    }

    public function playbackLogs() {
        return $this->hasMany(PlaybackLog::class, 'screen_id', 'screen_id');
    }
}