<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Street extends Model {
    protected $table = 'streets';
    protected $primaryKey = 'street_id';
    public $timestamps = false;
    protected $fillable = ['region_id', 'name'];

    public function region() {
        return $this->belongsTo(Region::class, 'region_id', 'region_id');
    }

    public function screens() {
        return $this->hasMany(Screen::class, 'street_id', 'street_id');
    }
}
