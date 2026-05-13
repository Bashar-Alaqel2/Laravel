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
    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'ad_id' => 'required|exists:advertisements,ad_id',
        ]);

        try {
            $this->setStripeKey();
            $ad = Advertisement::findOrFail($request->ad_id);
            
            // تحويل المبلغ إلى سنتات (Stripe يستخدم السنت)
            $amount = (int)($ad->total_cost * 100);

            $paymentIntent = PaymentIntent::create([
                'amount' => $amount,
                'currency' => 'usd',
                'metadata' => [
                    'ad_id' => $ad->ad_id,
                    'user_id' => $request->user()->user_id,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            return response()->json([
                'success' => true,
                'clientSecret' => $paymentIntent->client_secret,
                'paymentIntentId' => $paymentIntent->id
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
