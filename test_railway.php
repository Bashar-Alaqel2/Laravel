<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;

$user = \App\Models\User::find(4);
if (!$user) {
    die("User ID 4 not found!\n");
}

$tokenResult = $user->createToken('TestScript');
$token = $tokenResult->plainTextToken;

echo "Generated Token: " . $token . "\n\n";

echo "--- LOCAL API GET /api/screens ---\n";
try {
    $responseLocal = Http::withToken($token)
        ->get('http://127.0.0.1:9000/api/screens');
    echo "Status: " . $responseLocal->status() . "\n";
    echo "Body: " . $responseLocal->body() . "\n\n";
} catch (\Exception $e) {
    echo "Error Local: " . $e->getMessage() . "\n\n";
}

echo "--- RAILWAY API GET /api/screens ---\n";
try {
    $responseRailway = Http::withToken($token)
        ->get('https://laravel-production-969f.up.railway.app/api/screens');
    echo "Status: " . $responseRailway->status() . "\n";
    echo "Body: " . $responseRailway->body() . "\n\n";
} catch (\Exception $e) {
    echo "Error Railway: " . $e->getMessage() . "\n\n";
}
