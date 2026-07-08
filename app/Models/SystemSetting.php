<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model {
    protected $table = 'system_settings';
    protected $primaryKey = 'setting_id';
    public $timestamps = false;
    protected $fillable = ['setting_key', 'setting_value', 'description'];
}
