<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Advertisement extends Model {
    protected $table = 'advertisements';
    protected $primaryKey = 'ad_id';
    public $timestamps = false; // using uploaded_at manually
    protected $fillable = ['advertiser_id', 'category_id', 'title', 'file_path', 'duration', 'file_size', 'status', 'rejection_reason', 'is_deleted'];

    public function advertiser() {
        return $this->belongsTo(User::class, 'advertiser_id', 'user_id');
    }

    public function category() {
        return $this->belongsTo(Category::class, 'category_id', 'category_id');
    }

    public function screens() {
        return $this->belongsToMany(Screen::class, 'ad_screens', 'ad_id', 'screen_id')
                    ->withPivot('price');
    }

    public function schedules() {
        return $this->hasMany(AdSchedule::class, 'ad_id', 'ad_id');
    }

    public function playbackLogs() {
        return $this->hasMany(PlaybackLog::class, 'ad_id', 'ad_id');
    }
}