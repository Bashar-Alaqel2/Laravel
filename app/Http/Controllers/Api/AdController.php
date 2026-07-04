<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Models\FinancialLedger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdController extends Controller
{
    // ==========================================
    // 1. جلب الإعلانات (Index)
    // ==========================================
    public function index(Request $request)
    {
        $user = $request->user();

        // الإدارة والسكرتارية يرون كل الإعلانات مع الشاشات والمواقع
        if ($user->can('manage_all') || $user->can('review_ads')) {
            $ads = Advertisement::with(['advertiser', 'screens.street.region.governorate', 'category'])
                                ->where('is_deleted', 'false')
                                ->get();
            return response()->json(['success' => true, 'data' => $ads], 200);
        }

        // المعلن يرى إعلاناته فقط
        if ($user->can('view_own_reports')) {
            $ads = Advertisement::with(['screens', 'category'])
                                ->where('advertiser_id', $user->user_id)
                                ->where('is_deleted', 'false')
                                ->get();
            return response()->json(['success' => true, 'data' => $ads], 200);
        }

        return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية لرؤية الإعلانات.'], 403);
    }

    // ==========================================
    // 2. رفع إعلان جديد (Store) مع خوارزمية Zero-Collision
    // ==========================================
    public function store(Request $request)
    {
        // التحقق من صلاحية "رفع الإعلانات"
        if (!$request->user()->can('create_campaigns')) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية لرفع الإعلانات.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'             => 'required|string|max:150',
            'advertiser_id'     => 'nullable|exists:users,user_id', // إذا لم يرسل، نستخدم الحالي
            'category_id'       => 'nullable|exists:categories,category_id',
            'duration'          => 'nullable|integer|min:1', 
            'file'              => 'required|file|mimes:mp4,mov,avi,jpeg,png,jpg|max:51200', 
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'target_start_time' => 'nullable|date_format:H:i', // جديد: استهداف وقت محدد
            'target_end_time'   => 'nullable|date_format:H:i', // جديد
            'interval_minutes'  => 'required|integer|min:1',   // جديد: كل كم دقيقة يعرض
            'total_cost'        => 'required|numeric',
            'package_name'      => 'nullable|string',
            'screen_ids'        => 'required|array',
            'screen_ids.*'      => 'exists:screens,screen_id',
            'receipt_url'       => 'nullable|url',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        try {
            $duration = $request->duration ?? 15; // افتراضياً 15 ثانية إذا لم يرسل الرياكت المدة
            // 1. حساب السعة المطلوبة بالثواني في الساعة الواحدة
            $interval = $request->interval_minutes;
            $allocatedSeconds = (60 / $interval) * $duration; // مثلاً (60/5) * 15 = 180 ثانية

            if ($allocatedSeconds > 3600) {
                return response()->json(['success' => false, 'message' => 'معدل التكرار ومدة الإعلان تتجاوز سعة الساعة الواحدة!'], 400);
            }

            // 2. التحقق من التضارب (Zero-Collision Algorithm) لكل شاشة
            $reqStartTime = $request->target_start_time ?? '00:00:00';
            $reqEndTime   = $request->target_end_time ?? '23:59:59';

            foreach ($request->screen_ids as $screenId) {
                // جلب مجموع الثواني المحجوزة مسبقاً في هذه الشاشة في نفس الوقت والتاريخ
                $usedSeconds = \App\Models\AdSchedule::whereHas('advertisement', function ($q) {
                        // نتجاهل الإعلانات المرفوضة والمحذوفة
                        $q->where('status', '!=', 'Rejected')->whereNull('deleted_at');
                    })
                    ->whereHas('advertisement.screens', function($q) use ($screenId) {
                        $q->where('screens.screen_id', $screenId);
                    })
                    ->where('is_active', 'true')
                    ->where('start_date', '<=', $request->end_date)
                    ->where('end_date', '>=', $request->start_date)
                    ->where(function ($query) use ($reqStartTime, $reqEndTime) {
                        // تداخل الأوقات: وقت بداية المحجوز أصغر من نهاية المطلوب، ووقت نهاية المحجوز أكبر من بداية المطلوب
                        $query->where(function($q) use ($reqStartTime, $reqEndTime) {
                            $q->where('start_time', '<', $reqEndTime)
                              ->where('end_time', '>', $reqStartTime);
                        })->orWhereNull('start_time'); // يشمل الإعلانات المفتوحة 24/7
                    })
                    ->sum('allocated_seconds');

                if (($usedSeconds + $allocatedSeconds) > 3600) {
                    $available = 3600 - $usedSeconds;
                    return response()->json([
                        'success' => false, 
                        'message' => "نعتذر، الشاشة رقم {$screenId} ممتلئة ولا تتسع لإعلانك في هذا الوقت. السعة المتبقية: {$available} ثانية في الساعة."
                    ], 400);
                }
            }

            // تحديد الحالة الأولية
            $initialStatus = $request->filled('receipt_url') ? 'Pending' : 'waiting_payment';

            // رفع الملف المحلي
            $path = $request->file('file')->store('ads', 'public');
            $sizeInMB = $request->file('file')->getSize() / 1024 / 1024;

            $ad = Advertisement::create([
                'advertiser_id'   => $request->advertiser_id ?? $request->user()->user_id,
                'category_id'     => $request->category_id,
                'title'           => $request->title,
                'file_path'       => '/storage/' . $path,
                'duration'        => $duration,
                'file_size'       => round($sizeInMB, 2),
                'start_date'      => $request->start_date,
                'end_date'        => $request->end_date,
                'daily_frequency' => $request->interval_minutes, // استخدمنا الحقل كمتغير مؤقت
                'total_cost'      => $request->total_cost, // يمكن لاحقاً حسابها من السيرفر مباشرة عبر screen_pricing_slots
                'package_name'    => $request->package_name,
                'status'          => $initialStatus,
            ]);

            // ربط الشاشات
            $ad->screens()->sync($request->screen_ids);

            // إنشاء الجدولة الزمنية الدقيقة (AdSchedule)
            \App\Models\AdSchedule::create([
                'ad_id'             => $ad->ad_id,
                'start_date'        => $request->start_date,
                'end_date'          => $request->end_date,
                'start_time'        => $request->target_start_time,
                'end_time'          => $request->target_end_time,
                'interval_minutes'  => $request->interval_minutes,
                'allocated_seconds' => $allocatedSeconds,
                'is_active' => 'true',
            ]);

            // تسجيل إيصال الدفع إن وجد وتحويله إلى Base64 لتخزينه في قاعدة البيانات
            if ($request->hasFile('receipt')) {
                $file = $request->file('receipt');
                $base64 = base64_encode(file_get_contents($file->getRealPath()));
                $mime = $file->getClientMimeType();
                $receiptData = "data:{$mime};base64,{$base64}";

                \App\Models\FinancialLedger::create([
                    'advertisement_id' => $ad->ad_id,
                    'user_id'          => $ad->advertiser_id ?? $request->user()->user_id,
                    'transaction_type' => 'payment_pending',
                    'amount'           => $ad->total_cost,
                    'status'           => 'pending',
                    'notes'            => "إيصال دفع مرفق عند إنشاء الإعلان: {$ad->title}",
                    'receipt_path'     => $receiptData,
                ]);
            }

            // إرسال إشعارات
            if ($initialStatus === 'Pending') {
                $advertiser = $ad->advertiser ?? $request->user();
                \App\Models\Notification::create([
                    'user_id' => $ad->advertiser_id,
                    'title' => json_encode(['key' => 'notif_title_new_ad_pending']),
                    'message' => json_encode(['key' => 'notif_msg_new_ad_pending', 'args' => ['title' => $ad->title]]),
                    'is_read' => 'false',
                ]);

                $admins = \App\Models\User::whereHas('role', function($q) {
                    $q->whereIn('role_name', ['Admin', 'Secretary', 'SuperAdmin']);
                })->get();

                foreach ($admins as $admin) {
                    \App\Models\Notification::create([
                        'user_id' => $admin->user_id,
                        'title' => json_encode(['key' => 'notif_title_ad_pending_review']),
                        'message' => json_encode(['key' => 'notif_msg_ad_pending_review', 'args' => ['advertiser' => $advertiser->full_name, 'title' => $ad->title]]),
                        'is_read' => 'false',
                    ]);

                    if ($request->hasFile('receipt')) {
                        \App\Models\Notification::create([
                            'user_id' => $admin->user_id,
                            'title' => json_encode(['key' => 'notif_title_new_receipt']),
                            'message' => json_encode(['key' => 'notif_msg_new_receipt', 'args' => ['cost' => $ad->total_cost, 'advertiser' => $advertiser->full_name]]),
                            'is_read' => 'false',
                        ]);
                    }
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'تم استلام الإعلان بنجاح وتم تأكيد حجز الوقت في الشاشات. ' . ($request->hasFile('receipt') ? 'بانتظار مراجعة الإيصال.' : ''),
                'ad'      => $ad
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'خطأ في السيرفر: ' . $e->getMessage()], 500);
        }
    }

    // ==========================================
    // 3. الموافقة أو رفض الإعلان (Update Status)
    // ==========================================
    public function updateStatus(Request $request, $id)
    {
        // فقط السكرتير أو الآدمن يستطيع التغيير
        if (!$request->user()->can('approve_ads') && !$request->user()->can('manage_all')) {
            return response()->json(['error' => 'ليس لديك صلاحية لتغيير حالة الإعلانات.'], 403);
        }

        $request->validate([
            'status' => 'required|in:Active,Paused,Rejected',
            'reason' => 'required_if:status,Rejected|string|nullable'
        ]);

        $ad = Advertisement::find($id);
        if (!$ad || $ad->is_deleted) {
            return response()->json(['error' => 'الإعلان غير موجود.'], 404);
        }

        $ad->status = $request->status;
        if ($request->status === 'Rejected') {
            $ad->rejection_reason = $request->reason;
        } else {
            $ad->rejection_reason = null;
        }
        $ad->save();

        // إرسال إشعار للمعلن
        if ($ad->status === 'Active') {
            \App\Models\Notification::create([
                'user_id' => $ad->advertiser_id,
                'title' => json_encode(['key' => 'notif_title_ad_approved']),
                'message' => json_encode(['key' => 'notif_msg_ad_approved', 'args' => ['title' => $ad->title]]),
                'is_read' => 'false',
            ]);

            // إرسال إشعار لملاك الشاشات المرتبطة بالإعلان
            $schedule = $ad->schedules()->first();
            foreach ($ad->screens as $screen) {
                if ($screen->owner_id) {
                    \App\Models\Notification::create([
                        'user_id' => $screen->owner_id,
                        'title' => json_encode(['key' => 'notif_title_ad_scheduled']),
                        'message' => json_encode(['key' => 'notif_msg_ad_scheduled', 'args' => ['title' => $ad->title, 'screen' => $screen->screen_name, 'start' => $schedule->start_date]]),
                        'is_read' => 'false',
                    ]);
                }
            }
        } elseif ($ad->status === 'Rejected') {
            \App\Models\Notification::create([
                'user_id' => $ad->advertiser_id,
                'title' => json_encode(['key' => 'notif_title_ad_rejected']),
                'message' => json_encode(['key' => 'notif_msg_ad_rejected', 'args' => ['title' => $ad->title, 'reason' => $ad->rejection_reason]]),
                'is_read' => 'false',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "تم تغيير حالة الإعلان بنجاح.",
            'data'      => $ad
        ], 200);
    }

    // ==========================================
    // 4. حذف إعلان (Delete)
    // ==========================================
    public function destroy(Request $request, $id)
    {
        $ad = Advertisement::find($id);
        if (!$ad || $ad->is_deleted) {
            return response()->json(['error' => 'الإعلان غير موجود.'], 404);
        }

        // من يحق له الحذف؟ المعلن صاحب الإعلان أو الإدارة
        if ($ad->advertiser_id !== $request->user()->user_id && !$request->user()->can('manage_all')) {
            return response()->json(['error' => 'لا تملك صلاحية حذف هذا الإعلان.'], 403);
        }

        // الحذف المنطقي (Soft Delete)
        $ad->is_deleted = true;
        $ad->deleted_at = now();
        $ad->save();

        return response()->json(['message' => 'تم حذف الإعلان بنجاح.'], 200);
    }

    // ==========================================
    // محرك التسعير الذكي (Smart Pricing Engine v2)
    // POST /api/ads/calculate-cost
    // ==========================================
    public function calculateCost(Request $request)
    {
        $request->validate([
            'screen_ids'        => 'required|array|min:1',
            'screen_ids.*'      => 'exists:screens,screen_id',
            'start_date'        => 'required|date',
            'end_date'          => 'required|date|after_or_equal:start_date',
            'target_start_time' => 'nullable|date_format:H:i',
            'target_end_time'   => 'nullable|date_format:H:i',
            'interval_minutes'  => 'required|integer|min:1',
        ]);

        // ── 1. عدد الأيام
        $startDate = new \DateTime($request->start_date);
        $endDate   = new \DateTime($request->end_date);
        $days = (int) $startDate->diff($endDate)->days + 1;

        // ── 2. نطاق الوقت المستهدف
        $reqStartTime = $request->target_start_time ?? '00:00';
        $reqEndTime   = $request->target_end_time   ?? '23:59';
        if (strlen($reqStartTime) === 5) $reqStartTime .= ':00';
        if (strlen($reqEndTime)   === 5) $reqEndTime   .= ':00';

        // ── 3. مضاعف باقة التكرار
        $frequency         = (int) $request->interval_minutes;
        $package           = \App\Models\FrequencyPackage::where('display_interval', $frequency)->first();
        $packageMultiplier = $package ? (double) $package->price_multiplier : 1.0;

        // ── 4. عدد المعلنين المشتركين في نفس الوقت (لكل شاشة)
        $sharedCountMap = [];
        foreach ($request->screen_ids as $screenId) {
            $sharedCount = \App\Models\AdSchedule::whereHas('advertisement', function ($q) {
                    $q->whereNotIn('status', ['Rejected'])->whereNull('deleted_at');
                })
                ->whereHas('advertisement.screens', function ($q) use ($screenId) {
                    $q->where('screens.screen_id', $screenId);
                })
                ->where('is_active', 'true')
                ->where('start_date', '<=', $request->end_date)
                ->where('end_date',   '>=', $request->start_date)
                ->where(function ($q) use ($reqStartTime, $reqEndTime) {
                    $q->where(function ($inner) use ($reqStartTime, $reqEndTime) {
                        $inner->where('start_time', '<', $reqEndTime)
                              ->where('end_time',   '>', $reqStartTime);
                    })->orWhereNull('start_time');
                })
                ->count();

            // المعلن الحالي سيُضاف، لذلك نضيف 1
            $sharedCountMap[$screenId] = $sharedCount + 1;
        }

        // ── 5. حساب التكلفة لكل شاشة
        $totalCost     = 0.0;
        $screenDetails = [];

        foreach ($request->screen_ids as $screenId) {
            $screen = \App\Models\Screen::with('type')->find($screenId);
            if (!$screen) continue;

            // أ. السعر الأساسي اليومي من إعدادات الشاشة
            $basePrice = (double) ($screen->base_price ?? 10.0);

            // ب. مضاعف حجم الشاشة (55"=1.0x | 65"=1.1x | 75"=1.2x | 86"=1.35x | 98+"=1.5x)
            $sizeInch = (int) ($screen->screen_size_inch ?? 55);
            $sizeMultiplier = match(true) {
                $sizeInch >= 98 => 1.5,
                $sizeInch >= 86 => 1.35,
                $sizeInch >= 75 => 1.2,
                $sizeInch >= 65 => 1.1,
                default         => 1.0,
            };

            // ج. مضاعف وقت الذروة (Peak Time Multiplier)
            $peakMultiplier   = 1.0;
            $overlappingSlots = \App\Models\ScreenPricingSlot::where('screen_id', $screenId)
                ->where(function ($q) use ($reqStartTime, $reqEndTime) {
                    $q->where('start_time', '<', $reqEndTime)
                      ->where('end_time',   '>', $reqStartTime);
                })->get();

            foreach ($overlappingSlots as $slot) {
                if ((double) $slot->price_multiplier > $peakMultiplier) {
                    $peakMultiplier = (double) $slot->price_multiplier;
                }
            }

            // د. مضاعف التشارك (1 معلن=1.0x | 2 معلنين=0.65x | 3+=0.5x)
            $sharedCount = $sharedCountMap[$screenId];
            $sharingMultiplier = match(true) {
                $sharedCount >= 3  => 0.50,
                $sharedCount === 2 => 0.65,
                default            => 1.0,
            };

            // ── المعادلة النهائية:
            // السعر الأساسي × حجم الشاشة × وقت الذروة × باقة التكرار × التشارك × الأيام
            $screenTotal = $basePrice
                         * $sizeMultiplier
                         * $peakMultiplier
                         * $packageMultiplier
                         * $sharingMultiplier
                         * $days;

            $totalCost += $screenTotal;

            $screenDetails[] = [
                'screen_id'          => $screenId,
                'screen_name'        => $screen->screen_name,
                'base_price'         => $basePrice,
                'size_inch'          => $sizeInch,
                'size_multiplier'    => $sizeMultiplier,
                'peak_multiplier'    => $peakMultiplier,
                'package_multiplier' => $packageMultiplier,
                'sharing_discount'   => $sharingMultiplier < 1.0
                    ? round((1 - $sharingMultiplier) * 100) . '% خصم تشارك'
                    : 'لا يوجد خصم',
                'shared_advertisers' => $sharedCount,
                'screen_total'       => round($screenTotal, 2),
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'days'               => $days,
                'frequency_minutes'  => $frequency,
                'package_multiplier' => $packageMultiplier,
                'total_cost'         => round($totalCost, 2),
                'screens'            => $screenDetails,
            ]
        ]);
    }
}
