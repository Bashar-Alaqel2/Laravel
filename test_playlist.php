<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$screen = \App\Models\Screen::where('mac_address', 'SB-J9NDUS')->first();
$nowDate = now()->toDateString();

$ads = $screen->advertisements;
echo "Total ads: " . count($ads) . "\n";

$ads2 = $screen->advertisements()->whereIn('status', ['Active', 'Approved'])->get();
echo "Active/Approved ads: " . count($ads2) . "\n";

$ads3 = $screen->advertisements()->whereIn('status', ['Active', 'Approved'])->whereNull('advertisements.deleted_at')->get();
echo "Active/Approved/NotDeleted: " . count($ads3) . "\n";

$ads4 = $screen->advertisements()->whereIn('status', ['Active', 'Approved'])->whereNull('advertisements.deleted_at')
    ->whereHas('schedules', function ($q) use ($nowDate) {
        $q->where('start_date', '<=', $nowDate);
    })->get();
echo "Active/Approved/NotDeleted + start_date <= today: " . count($ads4) . "\n";

$ads5 = $screen->advertisements()->whereIn('status', ['Active', 'Approved'])->whereNull('advertisements.deleted_at')
    ->whereHas('schedules', function ($q) use ($nowDate) {
        $q->where('end_date', '>=', $nowDate);
    })->get();
echo "Active/Approved/NotDeleted + end_date >= today: " . count($ads5) . "\n";

$ads6 = $screen->advertisements()->whereIn('status', ['Active', 'Approved'])->whereNull('advertisements.deleted_at')
    ->whereHas('schedules', function ($q) use ($nowDate) {
        $q->where('is_active', 'true');
    })->get();
echo "Active/Approved/NotDeleted + is_active = 'true': " . count($ads6) . "\n";

