<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\Advertisement;
use App\Models\FinancialLedger;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripePaymentController extends Controller
{
    /**
     * إنشاء PaymentIntent لعملية دفع جديدة
     */
    public function createPaymentIntent(Request $request)
    {
        $request->validate(['ad_id' => 'required|exists:advertisements,ad_id']);
        
        $ad = Advertisement::findOrFail($request->ad_id);

        // جلب وسيلة دفع Stripe النشطة التي تحتوي على مفتاح سري
        $paymentMethod = PaymentMethod::whereNotNull('stripe_secret_key')
            ->where('stripe_secret_key', '!=', '')
            ->first();

        if (!$paymentMethod) {
            return response()->json(['success' => false, 'message' => 'لم يتم إعداد مفاتيح Stripe في لوحة الإدارة'], 400);
        }

        // استخدام المفتاح السري الذي أدخله المدير في الواجهة
        Stripe::setApiKey($paymentMethod->stripe_secret_key);

        try {
            $intent = PaymentIntent::create([
                'amount' => (int)($ad->total_cost * 100), // تحويل الدولار إلى سنت
                'currency' => 'usd',
                'metadata' => ['ad_id' => $ad->ad_id],
                'automatic_payment_methods' => ['enabled' => true],
            ]);

            return response()->json([
                'success' => true,
                'clientSecret' => $intent->client_secret,
            ]);
        } catch (\Exception $e) {
            Log::error('Stripe Intent Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * تأكيد الدفع بعد نجاح العملية في التطبيق (Webhook alternative)
     */
    public function confirmPayment(Request $request)
    {
        $request->validate([
            'ad_id' => 'required|exists:advertisements,ad_id',
            'payment_intent_id' => 'required'
        ]);

        try {
            DB::beginTransaction();

            $ad = Advertisement::findOrFail($request->ad_id);
            
            // تحديث حالة الإعلان
            $ad->update(['status' => 'pending']); // ينتقل للمراجعة بعد الدفع

            // تسجيل العملية في السجل المالي
            FinancialLedger::create([
                'user_id' => $ad->advertiser_id,
                'type' => 'income',
                'amount' => $ad->total_cost,
                'description' => 'دفع قيمة إعلان عبر Stripe: ' . $ad->title,
                'status' => 'completed'
            ]);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'تم تأكيد الدفع بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
