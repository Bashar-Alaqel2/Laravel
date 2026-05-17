<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $users = \App\Models\User::with('role')->whereHas('role', function($query) {
        $query->where('role_name', 'SuperAdmin');
    })->get(['user_id', 'role_id', 'full_name', 'email']);
    echo "SUCCESS USERS: " . count($users);
} catch (\Exception $e) {
    echo "ERROR USERS: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
}
