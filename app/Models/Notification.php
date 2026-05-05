<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model {
    protected $table = 'notifications';
    protected $primaryKey = 'notification_id';
    public const UPDATED_AT = null;
    protected $fillable = ['user_id', 'title', 'message', 'is_read'];

    public function user() {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}