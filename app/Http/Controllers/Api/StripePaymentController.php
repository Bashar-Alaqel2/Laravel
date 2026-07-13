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
        $request->validate([
            'ad_id' => 'required|exists:advertisements,ad_id',
            'payment_method_id' => 'required|exists:payment_methods,method_id'
        ]);
        
        $ad = Advertisement::findOrFail($request->ad_id);

        // جلب البوابة المحددة
        $paymentMethod = PaymentMethod::findOrFail($request->payment_method_id);

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
            
            // تسجيل العملية في السجل المالي
            $ledger = FinancialLedger::create([
                'advertisement_id' => $ad->ad_id,
                'user_id'          => $ad->advertiser_id,
                'transaction_type' => 'payment_in',
                'amount'           => $ad->total_cost,
                'payment_method'   => 'stripe',
                'reference_number' => $request->payment_intent_id,
                'notes'            => 'دفع قيمة إعلان عبر Stripe: ' . $ad->title,
                'status'           => 'completed'
            ]);

            // تحديث حالة الدفع في الإعلان لتصبح نشطة
            $ad->update([
                'payment_status' => 'paid',
                'payment_method' => 'stripe',
                'status' => 'Active' // يبدأ العرض فوراً بعد الدفع
            ]);

            // إشعار الشاشات بضرورة تحديث قائمة التشغيل فوراً
            foreach ($ad->screens as $screen) {
                \Illuminate\Support\Facades\DB::table('screen_commands')->insert([
                    'target_screen' => $screen->mac_address,
                    'command'       => 'SYNC_PLAYLIST',
                    'created_at'    => now(),
                    'updated_at'    => now(),
                ]);
            }

            // إرسال إشعار للمعلن
            \App\Models\Notification::create([
                'user_id' => $ad->advertiser_id,
                'title' => json_encode(['key' => 'notif_title_payment_confirmed']),
                'message' => json_encode(['key' => 'notif_msg_payment_confirmed', 'args' => ['amount' => $ledger->amount, 'title' => $ad->title]]),
                'is_read' => false,
            ]);

            // إرسال إشعار للإدارة
            $admins = \App\Models\User::whereHas('role', function($q) {
                $q->whereIn('role_name', ['Admin', 'Secretary', 'SuperAdmin']);
            })->get();

            foreach ($admins as $admin) {
                \App\Models\Notification::create([
                    'user_id' => $admin->user_id,
                    'title' => json_encode(['key' => 'notif_title_ad_paid_online']),
                    'message' => json_encode(['key' => 'notif_msg_ad_paid_online', 'args' => ['title' => $ad->title]]),
                    'is_read' => false,
                ]);
            }

            // توزيع الأرباح على ملاك الشاشات
            app(\App\Http\Controllers\Api\FinancialController::class)->distributeEarnings($ad, $ad->total_cost);

            DB::commit();
            return response()->json(['success' => true, 'message' => 'تم تأكيد الدفع وتوزيع الأرباح بنجاح']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}
