<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Screen extends Model {
    use SoftDeletes;
    protected $table = 'screens';
    protected $primaryKey = 'screen_id';
    public $timestamps = false;
    protected $fillable = ['owner_id', 'type_id', 'street_id', 'screen_name', 'image_path', 'status', 'linked_by', 'linked_at', 'disconnected_at', 'mac_address', 'pairing_code'];

    // دالة ديناميكية لحساب حالة الشاشة بشكل لحظي (Real-time) بناءً على آخر نبضة (Ping)
    public function getStatusAttribute($value)
    {
        // الحالات الإدارية تظل كما هي ولا نغيرها
        if (in_array($value, ['pending_activation', 'Maintenance'])) {
            return $value;
        }

        // إذا لم يكن هناك أي نبضة سابقة
        if (!$this->linked_at) {
            return 'Offline';
        }

        // إذا كانت آخر نبضة (Ping) منذ أقل من أو يساوي 3 دقائق، نعتبرها متصلة (Online)
        // لأن تطبيق الشاشة يرسل نبضة كل دقيقة واحدة
        if (\Carbon\Carbon::parse($this->linked_at)->diffInMinutes(now()) <= 3) {
            return 'Online';
        }

        return 'Offline';
    }

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
        return $this->belongsToMany(Advertisement::class, 'advertisement_screen', 'screen_id', 'ad_id')
                    ->withTimestamps();
    }

    public function playbackLogs() {
        return $this->hasMany(PlaybackLog::class, 'screen_id', 'screen_id');
    }
}