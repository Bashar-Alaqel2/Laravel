<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admins = \App\Models\User::whereHas('role', function($q) {
    $q->whereIn('role_name', ['Admin', 'Secretary', 'SuperAdmin']);
})->get();

foreach ($admins as $admin) {
    echo "Admin ID: " . $admin->user_id . " Name: " . $admin->full_name . " Role: " . $admin->role->role_name . "\n";
}
