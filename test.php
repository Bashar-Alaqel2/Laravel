<?php
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$ads = App\Models\Advertisement::with(['advertiser', 'screens.street.region.governorate', 'category'])
    ->where('is_deleted', \Illuminate\Support\Facades\DB::raw('false'))
    ->get();

$ads->each(function($ad) {
    if($ad->screens) {
        $ad->screens->each->makeHidden(['image_path']);
    }
});

echo "Ads size: " . strlen($ads->toJson()) . "\n";

