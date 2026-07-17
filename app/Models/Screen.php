<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Screen extends Model {
    use SoftDeletes;
    protected $table = 'screens';
    protected $primaryKey = 'screen_id';
    public $timestamps = false;
    protected $fillable = ['owner_id', 'type_id', 'street_id', 'screen_name', 'base_price', 'screen_size_inch', 'image_path', 'status', 'linked_by', 'linked_at', 'disconnected_at', 'mac_address', 'pairing_code', 'latitude', 'longitude', 'last_screenshot_url', 'last_screenshot_at'];

    // إضافة الحالة المحسوبة تلقائياً عند تحويل المودل لـ JSON
    protected $appends = ['computed_status'];

    /**
     * حساب حالة الشاشة ديناميكياً بناءً على آخر نبضة (Ping)
     * يُستخدم عبر $screen->computed_status (لا يتعارض مع استعلامات WHERE على العمود الأصلي)
     */
    public function getComputedStatusAttribute()
    {
        $rawStatus = $this->attributes['status'] ?? null;

        // الحالات الإدارية تظل كما هي ولا نغيرها
        if (in_array($rawStatus, ['pending_activation', 'Maintenance'])) {
            return $rawStatus;
        }

        // إذا لم يكن هناك أي نبضة سابقة
        if (!$this->linked_at) {
            return 'Offline';
        }

        // إذا كانت آخر نبضة منذ أقل من 3 دقائق = متصلة
        if (\Carbon\Carbon::parse($this->linked_at)->diffInMinutes(now()) <= 3) {
            return 'Online';
        }

        return 'Offline';
    }

    /**
     * Scope لجلب الشاشات المتصلة فعلياً (نبضة خلال 3 دقائق)
     */
    public function scopeReallyOnline($query)
    {
        return $query->where('linked_at', '>=', now()->subMinutes(3))
                     ->whereNotIn('status', ['pending_activation', 'Maintenance']);
    }

    /**
     * Scope لجلب الشاشات المنقطعة فعلياً
     */
    public function scopeReallyOffline($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('linked_at')
              ->orWhere('linked_at', '<', now()->subMinutes(3));
        })->whereNotIn('status', ['pending_activation', 'Maintenance']);
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

