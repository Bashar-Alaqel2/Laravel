<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Let's get an ad schedule that we know is active
$schedule = \App\Models\AdSchedule::first();
echo "First schedule is_active: " . ($schedule->is_active ? 'true' : 'false') . "\n";
echo "First schedule ID: " . $schedule->schedule_id . "\n";

// Test matching with string 'true'
$countString = \App\Models\AdSchedule::where('is_active', 'true')->count();
echo "Count with 'true' string: " . $countString . "\n";

// Test matching with true boolean (which we know crashes but let's test DB raw)
try {
    $countBool = \App\Models\AdSchedule::where('is_active', true)->count();
    echo "Count with true boolean: " . $countBool . "\n";
} catch (\Exception $e) {
    echo "Count with true boolean crashed: " . $e->getMessage() . "\n";
}

// How to properly query boolean in Laravel for Postgres?
// Use DB::raw('true')
$countRaw = \App\Models\AdSchedule::where('is_active', DB::raw('true'))->count();
echo "Count with DB::raw('true'): " . $countRaw . "\n";
