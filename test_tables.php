<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$columns1 = DB::getSchemaBuilder()->getColumnListing('ad_screens');
echo "ad_screens columns: " . implode(', ', $columns1) . "\n";

$columns2 = DB::getSchemaBuilder()->getColumnListing('advertisement_screen');
echo "advertisement_screen columns: " . implode(', ', $columns2) . "\n";
