<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$schedule = \App\Models\AdSchedule::where('ad_id', 39)->first();
echo "Start Date: " . $schedule->start_date . "\n";
echo "End Date: " . $schedule->end_date . "\n";
echo "Is Active: " . $schedule->is_active . "\n";
echo "nowDate: " . now()->toDateString() . "\n";
