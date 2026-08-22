<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DefaultContent extends Model
{
    protected $table = 'default_contents';
    protected $primaryKey = 'content_id';
    
    protected $fillable = [
        'title',
        'file_path',
        'file_type',
        'duration',
        'is_active',
        'screen_id'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'duration' => 'integer',
        'screen_id' => 'integer',
    ];

    public function screen()
    {
        return $this->belongsTo(Screen::class, 'screen_id', 'screen_id');
    }
}
