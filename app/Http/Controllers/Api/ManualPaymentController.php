<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Models\FinancialLedger;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Validator;

class ManualPaymentController extends Controller
{
    /**
     * استلام سند الدفع اليدوي من المعلن
     */
    public function store(Request $request)
    {
        // 1. التحقق من البيانات
        $validator = Validator::make($request->all(), [
            'ad_id' => 'required|exists:advertisements,ad_id',
            'payment_method_id' => 'required|exists:payment_methods,method_id',
            'receipt_image' => 'required|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        $ad = Advertisement::findOrFail($request->ad_id);
        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);
        
        // التحقق أن الإعلان يخص نفس المستخدم أو المستخدم لديه صلاحية
        if ($ad->advertiser_id !== $request->user()->user_id && !$request->user()->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'لا تملك صلاحية على هذا الإعلان'], 403);
        }

        // 2. معالجة السند (تحويله إلى Base64 كما هو متبع في النظام لتجنب إعدادات التخزين السحابي)
        $imagePath = null;
        if ($request->hasFile('receipt_image')) {
            $file = $request->file('receipt_image');
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $mime = $file->getClientMimeType();
            $imagePath = "data:{$mime};base64,{$base64}";
        } else {
            return response()->json(['success' => false, 'message' => 'صورة السند مطلوبة'], 422);
        }

        // 3. إدراج العملية في الدفتر المالي (Financial Ledger)
        $ledger = FinancialLedger::create([
            'advertisement_id' => $ad->ad_id,
            'user_id' => $request->user()->user_id,
            'transaction_type' => 'payment_pending',
            'amount' => $ad->total_cost,
            'payment_method' => $paymentMethod->name,
            'receipt_path' => $imagePath,
            'status' => 'pending'
        ]);

        // 4. إرسال إشعار للمدير بوجود حوالة تحتاج إلى مراجعة
        $admins = \App\Models\User::whereHas('role', function($q) {
            $q->whereIn('role_id', [\App\Models\Role::ADMIN, \App\Models\Role::SECRETARY, \App\Models\Role::SUPER_ADMIN]);
        })->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->user_id,
                'title' => json_encode(['key' => 'notif_title_manual_payment']), // يمكنك إضافة هذه المفاتيح لاحقاً للترجمة
                'message' => json_encode(['key' => 'notif_msg_manual_payment', 'args' => ['ad_id' => $ad->ad_id, 'method' => $paymentMethod->name]]),
                'is_read' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم استلام سند التحويل بنجاح، ستتم مراجعة الدفع قريباً',
            'data' => $ledger
        ], 201);
    }
}
