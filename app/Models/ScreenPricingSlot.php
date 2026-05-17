<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScreenPricingSlot extends Model
{
    protected $table = 'screen_pricing_slots';
    protected $primaryKey = 'slot_id';

    protected $fillable = [
        'screen_id',
        'start_time',
        'end_time',
        'price_multiplier',
    ];

    public function screen()
    {
        return $this->belongsTo(Screen::class, 'screen_id', 'screen_id');
    }
}
