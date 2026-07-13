<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\FinancialLedger;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

echo "Starting wallet synchronization...\n";

// Get all unique user IDs from the ledger
$userIds = FinancialLedger::select('user_id')->distinct()->pluck('user_id');

$count = 0;
foreach ($userIds as $userId) {
    // حساب إجمالي الأرباح المستحقة
    $totalEarnings = FinancialLedger::where('user_id', $userId)
        ->where('transaction_type', 'payout_pending')
        ->sum('amount');

    // حساب المبالغ المسحوبة بالكامل
    $withdrawn = FinancialLedger::where('user_id', $userId)
        ->where('transaction_type', 'payout_completed')
        ->sum('amount');

    // حساب المبالغ المعلقة (قيد السحب حالياً)
    $requested = FinancialLedger::where('user_id', $userId)
        ->where('transaction_type', 'payout_requested')
        ->sum('amount');

    $availableBalance = $totalEarnings - $withdrawn - $requested;
    $pendingBalance = $requested;

    // تحديث أو إنشاء المحفظة
    Wallet::updateOrCreate(
        ['user_id' => $userId],
        [
            'available_balance' => $availableBalance,
            'pending_balance'   => $pendingBalance
        ]
    );

    echo "Synced wallet for User ID {$userId}: Available={$availableBalance}, Pending={$pendingBalance}\n";
    $count++;
}

echo "Successfully synced {$count} wallets.\n";
