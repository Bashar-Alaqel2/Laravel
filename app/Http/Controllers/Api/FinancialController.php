<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FinancialLedger;
use App\Models\Advertisement;
use App\Models\Screen;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FinancialController extends Controller
{
    // ==========================================
    // 1. تسجيل عملية دفع (من المعلن)
    // ==========================================
    public function recordPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ad_id'            => 'required|exists:advertisements,ad_id',
            'amount'           => 'required|numeric|min:0.01',
            'payment_method'   => 'required|string',
            'reference_number' => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        $ad = Advertisement::findOrFail($request->ad_id);

        try {
            DB::beginTransaction();

            // 1. تسجيل الحركة في دفتر الأستاذ
            $ledger = FinancialLedger::create([
                'advertisement_id' => $ad->ad_id,
                'user_id'          => $ad->advertiser_id,
                'transaction_type' => 'payment_in',
                'amount'           => $request->amount,
                'payment_method'   => $request->payment_method,
                'reference_number' => $request->reference_number,
                'status'           => 'completed',
                'notes'            => $request->notes ?? "دفع قيمة الإعلان: {$ad->title}",
            ]);

            // 2. تحديث حالة الدفع في الإعلان
            $ad->update([
                'payment_status' => 'paid',
                'payment_method' => $request->payment_method,
            ]);

            // 3. (اختياري) توزيع الأرباح تلقائياً أو تركها لخطوة لاحقة
            $this->distributeEarnings($ad, $request->amount);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'تم تسجيل الدفع وتوزيع الأرباح بنجاح.',
                'data'    => $ledger
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'خطأ في معالجة الدفع: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 2. توزيع الأرباح على ملاك الشاشات
    // (يتم اقتطاع نسبة للمنصة والباقي للشاشات)
    // ==========================================
    private function distributeEarnings(Advertisement $ad, $totalAmount)
    {
        // نسبة المنصة (مثلاً 20%)
        $platformFeeRate = 0.20;
        $platformFee = $totalAmount * $platformFeeRate;
        $netToOwners = $totalAmount - $platformFee;

        // تسجيل عمولة المنصة
        FinancialLedger::create([
            'advertisement_id' => $ad->ad_id,
            'user_id'          => 1, // غالباً الآدمن الأول أو حساب المنصة
            'transaction_type' => 'platform_fee',
            'amount'           => $platformFee,
            'status'           => 'completed',
            'notes'            => "عمولة المنصة من إعلان: {$ad->title}",
        ]);

        // توزيع الباقي على الشاشات المرتبطة بالإعلان
        $screens = $ad->screens;
        if ($screens->count() > 0) {
            $amountPerScreen = $netToOwners / $screens->count();

            foreach ($screens as $screen) {
                if ($screen->owner_id) {
                    FinancialLedger::create([
                        'advertisement_id' => $ad->ad_id,
                        'screen_id'        => $screen->screen_id,
                        'user_id'          => $screen->owner_id,
                        'transaction_type' => 'payout_pending',
                        'amount'           => $amountPerScreen,
                        'status'           => 'pending', // بانتظار طلب السحب من المالك
                        'notes'            => "أرباح مستحقة عن شاشة: {$screen->screen_name}",
                    ]);
                }
            }
        }
    }

    // ==========================================
    // 3. جلب سجل الحركات المالية (للمدير أو المحاسب)
    // ==========================================
    public function getLedger(Request $request)
    {
        $query = FinancialLedger::with(['user', 'advertisement', 'screen']);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        $ledger = $query->orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $ledger], 200);
    }

    // ==========================================
    // 4. جلب أرباح مالك شاشة محدد
    // ==========================================
    public function getOwnerEarnings(Request $request)
    {
        $userId = $request->user()->user_id;
        
        $totalEarnings = FinancialLedger::where('user_id', $userId)
            ->where('transaction_type', 'payout_pending')
            ->sum('amount');

        $withdrawn = FinancialLedger::where('user_id', $userId)
            ->where('transaction_type', 'payout_completed')
            ->sum('amount');

        $pendingLogs = FinancialLedger::with('advertisement')
            ->where('user_id', $userId)
            ->where('transaction_type', 'payout_pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_earned' => $totalEarnings,
                'withdrawn'    => $withdrawn,
                'balance'      => $totalEarnings - $withdrawn,
                'history'      => $pendingLogs
            ]
        ], 200);
    }
}
