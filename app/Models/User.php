<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $table = 'users';
    protected $primaryKey = 'user_id';
    public const UPDATED_AT = null;

    protected $fillable = [
        'role_id',
        'full_name',
        'email',
        'phone',
        'location',
        'password_hash',
        'account_status'
    ];

    protected $hidden = [
        'password_hash',
    ];

    /**
     * Override to tell Laravel to use 'password_hash' column instead of 'password'
     */
    public function getAuthPasswordName()
    {
        return 'password_hash';
    }

    protected function casts(): array
    {
        return [
            'password_hash' => 'hashed',
        ];
    }

    // --- العلاقات (Relationships) ---

    public function role() {
        return $this->belongsTo(Role::class, 'role_id', 'role_id');
    }

    public function sessions() {
        return $this->hasMany(UserSession::class, 'user_id', 'user_id');
    }

    public function screens() {
        return $this->hasMany(Screen::class, 'owner_id', 'user_id'); // الشاشات التي يملكها
    }

    public function linkedScreens() {
        return $this->hasMany(Screen::class, 'linked_by', 'user_id'); // الشاشات التي قام بربطها
    }

    public function advertisements() {
        return $this->hasMany(Advertisement::class, 'advertiser_id', 'user_id');
    }

    public function invoices() {
        return $this->hasMany(Invoice::class, 'advertiser_id', 'user_id');
    }

    public function approvedTransactions() {
        return $this->hasMany(Transaction::class, 'approved_by', 'user_id');
    }

    public function wallet() {
        return $this->hasOne(Wallet::class, 'user_id', 'user_id');
    }

    public function notifications() {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }
}
