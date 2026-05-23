<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$count = DB::table('ad_screens')->where('ad_id', 37)->count(); // wait I don't know the ad_id
$ad = \App\Models\Advertisement::where('title', 'Test Ad from AI')->first();
if ($ad) {
    echo "Ad ID: " . $ad->ad_id . "\n";
    echo "Screens linked: " . $ad->screens()->count() . "\n";
} else {
    echo "Ad not found!\n";
}
