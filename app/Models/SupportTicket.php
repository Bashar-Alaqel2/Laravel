<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'screen_id',
        'subject',
        'category',
        'priority',
        'status',
        'description',
    ];

    protected $appends = ['created_at_human', 'updated_at_human'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function screen()
    {
        return $this->belongsTo(Screen::class, 'screen_id', 'screen_id');
    }

    public function getCreatedAtHumanAttribute()
    {
        return $this->created_at ? $this->created_at->diffForHumans() : null;
    }

    public function getUpdatedAtHumanAttribute()
    {
        return $this->updated_at ? $this->updated_at->diffForHumans() : null;
    }
}
