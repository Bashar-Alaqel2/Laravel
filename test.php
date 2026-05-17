<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $ledger = App\Models\FinancialLedger::with(['user', 'advertisement', 'screen'])->orderBy('created_at', 'desc')->get();
    echo "SUCCESS: " . count($ledger);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
