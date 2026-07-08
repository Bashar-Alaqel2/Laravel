<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ScreenType extends Model {
    protected $table = 'screen_types';
    protected $primaryKey = 'type_id';
    public $timestamps = false;
    protected $fillable = ['type_name', 'resolution_width', 'resolution_height', 'orientation'];

    public function screens() {
        return $this->hasMany(Screen::class, 'type_id', 'type_id');
    }
}
