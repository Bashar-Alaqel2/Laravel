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
    public function distributeEarnings(Advertisement $ad, $totalAmount)
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
        $user = $request->user();
        $query = FinancialLedger::with(['user', 'advertisement', 'screen']);

        if ($user) {
            if ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner')) {
                // ملاك الشاشات يشاهدون فقط حركاتهم الخاصة
                $query->where('user_id', $user->user_id);
            } elseif ($user->role && $user->role->role_name === 'Secretary') {
                // السكرتير لا يمكنه رؤية الخزينة أو الأرباح الكلية
                // يرى فقط الدفعات المعلقة التي تتطلب اتخاذ قرار (مراجعة الإيصال والاعتماد)
                $query->where('transaction_type', 'payment_pending');
            } else {
                // للمدراء أو الأدوار الإدارية الأخرى، تصفية بحسب المعامل الممرر إن وجد
                if ($request->has('user_id')) {
                    $query->where('user_id', $request->user_id);
                }
            }
        }

        if ($request->has('type')) {
            $query->where('transaction_type', $request->type);
        }

        // تحديد الأعمدة المطلوبة وتجاهل receipt_path لأنه قد يحتوي على Base64 ضخم جداً يبطئ النظام
        $query->select([
            'ledger_id', 'advertisement_id', 'screen_id', 'user_id', 
            'transaction_type', 'amount', 'payment_method', 'reference_number', 
            'status', 'notes', 'created_at', 'updated_at'
        ]);
        
        // إضافة حقل وهمي يخبر الواجهة ما إذا كان هناك صورة مرفقة أم لا
        $query->addSelect(\Illuminate\Support\Facades\DB::raw('CASE WHEN receipt_path IS NOT NULL THEN 1 ELSE 0 END as has_receipt'));

        $ledger = $query->orderBy('created_at', 'desc')->get();
        
        $totalPayments = $ledger->whereIn('transaction_type', ['payment', 'payment_in'])->where('status', 'completed')->sum('amount');
        
        return response()->json([
            'success' => true, 
            'data' => [
                'total_payments' => $totalPayments,
                'transactions'   => $ledger
            ]
        ], 200);
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
            
        $requested = FinancialLedger::where('user_id', $userId)
            ->where('transaction_type', 'payout_requested')
            ->sum('amount');
            
        $availableBalance = $totalEarnings - $withdrawn - $requested;

        $pendingLogs = FinancialLedger::with('advertisement')
            ->where('user_id', $userId)
            ->where('transaction_type', 'payout_pending')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'total_earnings' => $totalEarnings,
                'withdrawn' => $withdrawn,
                'available_balance' => $availableBalance,
                'pending_logs' => $pendingLogs
            ]
        ], 200);
    }
    
    // ==========================================
    // طلب سحب أرباح للمالك (Screen Owner)
    // ==========================================
    public function requestPayout(Request $request)
    {
        $user = $request->user();
        
        $request->validate([
            'amount' => 'required|numeric|min:50',
            'bank_name' => 'nullable|string',
            'account_number' => 'required|string'
        ]);

        $amount = $request->amount;
        
        $totalEarnings = FinancialLedger::where('user_id', $user->user_id)->where('transaction_type', 'payout_pending')->sum('amount');
        $withdrawn = FinancialLedger::where('user_id', $user->user_id)->where('transaction_type', 'payout_completed')->sum('amount');
        $requested = FinancialLedger::where('user_id', $user->user_id)->where('transaction_type', 'payout_requested')->sum('amount');
        
        $availableBalance = $totalEarnings - $withdrawn - $requested;
            
        if ($amount > $availableBalance) {
            return response()->json(['success' => false, 'message' => 'الرصيد المتاح غير كافٍ.'], 400);
        }

        FinancialLedger::create([
            'user_id' => $user->user_id,
            'transaction_type' => 'payout_requested',
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
            'status' => 'pending',
            'notes' => json_encode(['bank_name' => $request->bank_name, 'account_number' => $request->account_number])
        ]);

        return response()->json(['success' => true, 'message' => 'تم استلام طلب السحب بنجاح.']);
    }

    // ==========================================
    // 5. اعتماد دفعة (تغيير الحالة من Pending إلى Completed)
    // ==========================================
    public function approvePayment(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ledger = FinancialLedger::findOrFail($id);
            
            if ($ledger->transaction_type !== 'payment_pending') {
                return response()->json(['success' => false, 'message' => 'هذه العملية ليست دفعة معلقة.'], 400);
            }

            // 1. تحديث حالة القيد المالي
            $ledger->update([
                'status' => 'completed',
                'transaction_type' => 'payment_in'
            ]);

            // 2. تحديث حالة الإعلان
            if ($ledger->advertisement_id) {
                $ad = Advertisement::find($ledger->advertisement_id);
                if ($ad) {
                    $ad->update(['payment_status' => 'paid']);
                    
                    // إرسال إشعار للمعلن
                    \App\Models\Notification::create([
                        'user_id' => $ad->advertiser_id,
                        'title' => json_encode(['key' => 'notif_title_payment_confirmed']),
                        'message' => json_encode(['key' => 'notif_msg_payment_confirmed', 'args' => ['amount' => $ledger->amount, 'title' => $ad->title]]),
                        'is_read' => false,
                    ]);

                    // 3. توزيع الأرباح
                    $this->distributeEarnings($ad, $ledger->amount);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'تم اعتماد الدفع وتوزيع الأرباح بنجاح.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 6. رفض دفعة (تغيير الحالة من Pending إلى Rejected)
    // ==========================================
    public function rejectPayment(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $ledger = FinancialLedger::findOrFail($id);
            
            if ($ledger->transaction_type !== 'payment_pending') {
                return response()->json(['success' => false, 'message' => 'هذه العملية ليست دفعة معلقة.'], 400);
            }

            // 1. تحديث حالة القيد المالي
            $ledger->update([
                'status' => 'rejected'
            ]);

            // 2. تحديث حالة الإعلان
            if ($ledger->advertisement_id) {
                $ad = Advertisement::find($ledger->advertisement_id);
                if ($ad) {
                    $ad->update(['payment_status' => 'unpaid']);
                    
                    // إرسال إشعار للمعلن
                    \App\Models\Notification::create([
                        'user_id' => $ad->advertiser_id,
                        'title' => json_encode(['key' => 'notif_title_payment_rejected']),
                        'message' => json_encode(['key' => 'notif_msg_payment_rejected', 'args' => ['title' => $ad->title]]),
                        'is_read' => false,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'تم رفض الدفعة بنجاح.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 7. جلب صورة السند فقط (لتحسين أداء النظام)
    // ==========================================
    public function getReceipt($id)
    {
        $ledger = FinancialLedger::findOrFail($id);
        
        if (!$ledger->receipt_path) {
            return response()->json(['success' => false, 'message' => 'لا يوجد سند لهذه العملية.'], 404);
        }

        return response()->json([
            'success' => true,
            'receipt_path' => $ledger->receipt_path
        ], 200);
    }
}
