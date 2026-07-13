<?php

namespace App\Observers;

use App\Models\FinancialLedger;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;

class FinancialLedgerObserver
{
    /**
     * Handle the FinancialLedger "created" event.
     */
    public function created(FinancialLedger $ledger): void
    {
        $this->syncWallet($ledger->user_id);
    }

    /**
     * Handle the FinancialLedger "updated" event.
     */
    public function updated(FinancialLedger $ledger): void
    {
        $this->syncWallet($ledger->user_id);
    }

    /**
     * Handle the FinancialLedger "deleted" event.
     */
    public function deleted(FinancialLedger $ledger): void
    {
        $this->syncWallet($ledger->user_id);
    }

    /**
     * Sync the wallet balances for a specific user based on their ledger history.
     */
    private function syncWallet($userId): void
    {
        if (!$userId) return;

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
    }
}
