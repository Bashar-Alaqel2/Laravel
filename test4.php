<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $users = App\Models\User::with('role')->orderBy('created_at', 'desc')->get();
    echo "SUCCESS USERS LIST: " . count($users);
} catch (\Exception $e) {
    echo "ERROR USERS LIST: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
}
