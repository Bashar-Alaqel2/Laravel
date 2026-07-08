<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model {
    protected $table = 'user_sessions';
    protected $primaryKey = 'session_id';
    public const UPDATED_AT = null;
    protected $fillable = ['user_id', 'device_name', 'device_id', 'ip_address', 'fcm_token', 'last_active', 'is_revoked'];

    protected $casts = [
        'is_revoked' => \App\Casts\SmallIntBooleanCast::class,
    ];

    // Removed setIsRevokedAttribute to prevent Postgres crash with smallint

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
