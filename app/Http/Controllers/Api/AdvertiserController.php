<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Models\FinancialLedger;

class AdvertiserController extends Controller
{
    /**
     * جلب إحصائيات لوحة تحكم المعلن
     */
    public function getDashboard(Request $request)
    {
        $userId = $request->user()->user_id;

        // حساب الإعلانات النشطة
        $activeAdsCount = Advertisement::where('advertiser_id', $userId)
            ->where('status', 'Active')
            ->where('is_deleted', 0)
            ->count();

        // حساب الإعلانات قيد المراجعة (Pending أو waiting_payment)
        $pendingAdsCount = Advertisement::where('advertiser_id', $userId)
            ->whereIn('status', ['Pending', 'waiting_payment'])
            ->where('is_deleted', 0)
            ->count();

        // حساب إجمالي المصروفات (المدفوعات المعتمدة)
        $totalSpent = FinancialLedger::where('user_id', $userId)
            ->where('transaction_type', 'payment_in')
            ->where('status', 'completed')
            ->sum('amount');

        // جلب آخر 5 إعلانات حديثة
        $recentAds = Advertisement::where('advertiser_id', $userId)
            ->where('is_deleted', 0)
            ->orderBy('uploaded_at', 'desc')
            ->take(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'active_ads_count' => $activeAdsCount,
                'pending_ads_count' => $pendingAdsCount,
                'total_spent' => $totalSpent,
                'recent_ads' => $recentAds
            ]
        ], 200);
    }

    /**
     * جلب السجل المالي للمعلن
     */
    public function getFinancials(Request $request)
    {
        $userId = $request->user()->user_id;

        // إجمالي المدفوعات المعتمدة طوال الوقت
        $totalPayments = FinancialLedger::where('user_id', $userId)
            ->whereIn('transaction_type', ['payment_in', 'payment_pending'])
            ->where('status', 'completed')
            ->sum('amount');

        // الرصيد المعتمد (إن وجد، مثلاً إذا كان هناك نظام محفظة، هنا سنعتبره 0 أو نجلب الرصيد من جدول user_balances لو موجود)
        // حالياً سنفترض أنه دائماً 0 للمعلن لأنه يدفع مقابل الإعلان مباشرة
        $approvedBalance = 0; 

        // سجل العمليات
        $transactions = FinancialLedger::with('advertisement:ad_id,title')
            ->where('user_id', $userId)
            ->whereIn('transaction_type', ['payment_in', 'payment_pending'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($tx) {
                return [
                    'ledger_id' => $tx->ledger_id,
                    'date' => $tx->created_at->translatedFormat('d F Y'),
                    'time' => $tx->created_at->format('h:i A'),
                    'method' => $tx->payment_method ?? 'Unknown',
                    'amount' => $tx->amount,
                    'ref' => $tx->reference_number ?? 'AD-' . $tx->advertisement_id,
                    'status' => $tx->status === 'completed' ? 'معتمدة' : ($tx->status === 'pending' ? 'قيد المراجعة' : 'مرفوضة')
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'approved_balance' => $approvedBalance,
                'total_payments' => $totalPayments,
                'transactions' => $transactions
            ]
        ], 200);
    }
}
