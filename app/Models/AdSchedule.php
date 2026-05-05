<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AdSchedule extends Model {
    protected $table = 'ad_schedules';
    protected $primaryKey = 'schedule_id';
    public $timestamps = false;
    protected $fillable = ['ad_id', 'start_date', 'end_date', 'start_time', 'end_time', 'is_active'];

    public function advertisement() {
        return $this->belongsTo(Advertisement::class, 'ad_id', 'ad_id');
    }
}