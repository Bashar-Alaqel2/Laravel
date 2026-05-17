<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
try {
    $ledger = App\Models\FinancialLedger::with(['user', 'advertisement', 'screen'])->orderBy('created_at', 'desc')->get();
    $json = json_encode(['success' => true, 'data' => $ledger]);
    if ($json === false) {
        echo "JSON ERROR: " . json_last_error_msg();
    } else {
        echo "SUCCESS JSON";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
