<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ad = \App\Models\Advertisement::where('title', 'Test Ad from AI')->first();
if (!$ad) {
    echo "Ad not found!\n";
    exit;
}

echo "Ad ID: " . $ad->ad_id . "\n";
echo "Status: " . $ad->status . "\n";
echo "Deleted At: " . $ad->deleted_at . "\n";
echo "is_deleted: " . $ad->is_deleted . "\n";

$screens = $ad->screens;
echo "Linked screens: " . count($screens) . "\n";
foreach($screens as $s) {
    echo " - " . $s->mac_address . "\n";
}

$schedules = $ad->schedules;
echo "Schedules: " . count($schedules) . "\n";
foreach($schedules as $s) {
    echo " - " . $s->start_date . " to " . $s->end_date . " (Active: " . $s->is_active . ")\n";
}
