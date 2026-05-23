<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$ad = \App\Models\Advertisement::where('title', 'Test Ad 24 Hours')->first();
echo "Schedules for Test Ad 24 Hours: " . $ad->schedules()->count() . "\n";
