<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use App\Models\Advertisement;
use App\Models\FinancialLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StripePaymentController extends Controller
{
    private function setStripeKey()
    {
        $stripeMethod = PaymentMethod::where('is_active', true)
            ->whereNotNull('stripe_secret_key')
            ->first();

        if (!$stripeMethod) {
            throw new \Exception('لم يتم إعداد Stripe في النظام.');
        }

        Stripe::setApiKey($stripeMethod->stripe_secret_key);
    }

    /**
     * إنشاء PaymentIntent لعملية دفع جديدة
     */
    public function createIntent(Request $request)
    {
        $request->validate(['ad_id' => 'required|exists:advertisements,ad_id']);
        
        $ad = Advertisement::findOrFail($request->ad_id);

        // جلب وسيلة دفع Stripe النشطة التي تحتوي على مفتاح سري
        $paymentMethod = \App\Models\PaymentMethod::whereNotNull('stripe_secret_key')
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
            ]);

            return response()->json([
                'success' => true,
                'clientSecret' => $intent->client_secret,
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * معالجة الـ Webhook عند نجاح الدفع
     */
    public function handleWebhook(Request $request)
    {
        try {
            $this->setStripeKey();
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }

        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $event = null;

        try {
            // في البيئة التجريبية أو بدون Secret للـ Webhook، يمكننا قراءة البيانات مباشرة
            // ولكن يفضل استخدام مكتبة Stripe للتحقق
            $event = \Stripe\Event::constructFrom(
                json_decode($payload, true)
            );
        } catch(\UnexpectedValueException $e) {
            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // التعامل مع الحدث
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event->data->object;
                $this->processSuccessfulPayment($paymentIntent);
                break;
        }

        return response()->json(['status' => 'success']);
    }

    private function processSuccessfulPayment($paymentIntent)
    {
        $adId = $paymentIntent->metadata->ad_id;
        $userId = $paymentIntent->metadata->user_id;
        $amount = $paymentIntent->amount / 100;

        DB::transaction(function () use ($adId, $userId, $amount, $paymentIntent) {
            $ad = Advertisement::find($adId);
            if ($ad) {
                // 1. تحديث حالة الدفع للإعلان
                $ad->update(['payment_status' => 'paid']);

                // 2. تسجيل العملية في الدفتر المالي
                FinancialLedger::create([
                    'advertisement_id' => $adId,
                    'user_id' => $userId,
                    'transaction_type' => 'payment_in',
                    'amount' => $amount,
                    'payment_method' => 'stripe',
                    'reference_number' => $paymentIntent->id,
                    'status' => 'completed',
                    'notes' => 'دفع إلكتروني عبر Stripe'
                ]);

                // 3. توزيع الأرباح (يمكن استدعاء نفس منطق FinancialController)
                $financialController = new FinancialController();
                $financialController->distributeEarnings($ad, $amount);
            }
        });
    }
}
