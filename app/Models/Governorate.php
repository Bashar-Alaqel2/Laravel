<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Governorate extends Model {
    protected $table = 'governorates';
    protected $primaryKey = 'gov_id';
    public $timestamps = false;
    protected $fillable = ['name'];

    public function regions() {
        return $this->hasMany(Region::class, 'gov_id', 'gov_id');
    }
}