<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = \App\Models\Notification::count();

echo "Notification count: " . $count . "\n";

$notifications = \App\Models\Notification::latest()->take(5)->get();
foreach ($notifications as $n) {
    echo "ID: " . $n->notification_id . " | UserID: " . $n->user_id . " | Title: " . $n->title . " | Read: " . $n->is_read . "\n";
}
