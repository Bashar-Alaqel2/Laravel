<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Role extends Model {
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    public $timestamps = false;
    protected $fillable = ['role_name'];

    // Define role constants mapping to their default role_id
    public const SUPER_ADMIN = 1;
    public const ADVERTISER = 2;
    public const SCREEN_OWNER = 3;
    public const MAINTENANCE = 4;
    public const SECRETARY = 6;
    public const ADMIN = 7;

    public function users() {
        return $this->hasMany(User::class, 'role_id', 'role_id');
    }
}
