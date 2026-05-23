<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$screen = \App\Models\Screen::where('mac_address', 'SB-Y9N7K7')->first();
if (!$screen) {
    echo "Screen not found!\n";
    exit;
}
echo "Screen ID: " . $screen->screen_id . "\n";

$ad = \App\Models\Advertisement::where('title', 'Test Ad 24 Hours')->first();
if ($ad) {
    echo "Ad ID: " . $ad->ad_id . " is linked to: ";
    $linked = $ad->screens()->pluck('screens.screen_id')->toArray();
    echo implode(", ", $linked) . "\n";
    
    if (in_array($screen->screen_id, $linked)) {
        echo "YES! Linked to SB-Y9N7K7\n";
    } else {
        echo "NO! NOT LINKED TO SB-Y9N7K7\n";
        // Let's link it now!
        $ad->screens()->attach($screen->screen_id);
        echo "Linked it just now!\n";
    }
} else {
    echo "Test Ad 24 Hours not found!\n";
}

$nowDate = now()->toDateString();
$ads = $screen->advertisements()
    ->whereIn('status', ['Active', 'Approved'])
    ->whereNull('advertisements.deleted_at')
    ->whereHas('schedules', function ($q) use ($nowDate) {
        $q->where('is_active', 'true')
          ->where('start_date', '<=', $nowDate)
          ->where('end_date', '>=', $nowDate);
    })->get();

echo "Valid Playlist Ads count: " . count($ads) . "\n";
