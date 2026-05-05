<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Region extends Model {
    protected $table = 'regions';
    protected $primaryKey = 'region_id';
    public $timestamps = false;
    protected $fillable = ['gov_id', 'name'];

    public function governorate() {
        return $this->belongsTo(Governorate::class, 'gov_id', 'gov_id');
    }

    public function streets() {
        return $this->hasMany(Street::class, 'region_id', 'region_id');
    }
}