<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ad = \App\Models\Advertisement::create([
    'advertiser_id' => 1,
    'category_id' => 1,
    'title' => 'Test Ad 24 Hours',
    'file_path' => 'https://www.w3schools.com/html/mov_bbb.mp4',
    'duration' => 10,
    'file_size' => 1.5,
    'start_date' => now()->toDateString(),
    'end_date' => now()->toDateString(),
    'daily_frequency' => 5,
    'total_cost' => 10,
    'status' => 'Active'
]);
$screens = \App\Models\Screen::pluck('screen_id');
$ad->screens()->sync($screens);
\App\Models\AdSchedule::create([
    'ad_id' => $ad->ad_id,
    'start_date' => now()->toDateString(),
    'end_date' => now()->toDateString(),
    'start_time' => '00:00:00',
    'end_time' => '23:59:59',
    'interval_minutes' => 5,
    'allocated_seconds' => 120,
    'is_active' => 'true'
]);
DB::table('screen_commands')->insert([
    'target_screen' => 'all',
    'command' => 'SYNC_PLAYLIST',
    'created_at' => now(),
    'updated_at' => now()
]);
echo "Ad 24 Hours created successfully!\n";
