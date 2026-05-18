<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $statuses = \App\Models\Advertisement::pluck('status')->unique()->toArray();
    echo "UNIQUE STATUSES:\n";
    print_r($statuses);
    
    $ads = \App\Models\Advertisement::get();
    echo "TOTAL ADS: " . $ads->count() . "\n";
    foreach ($ads as $ad) {
        echo "Ad ID: {$ad->ad_id}, Title: {$ad->title}, Status: {$ad->status}, Start: {$ad->start_date}, End: {$ad->end_date}\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
