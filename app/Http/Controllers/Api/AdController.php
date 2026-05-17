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
                                ->where('is_deleted', false)
                                ->get();
            return response()->json(['success' => true, 'data' => $ads], 200);
        }

        // المعلن يرى إعلاناته فقط
        if ($user->can('view_own_reports')) {
            $ads = Advertisement::with(['screens', 'category'])
                                ->where('advertiser_id', $user->user_id)
                                ->where('is_deleted', false)
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
            'duration'          => 'required|integer|min:1', 
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
            'receipt'           => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        try {
            // 1. حساب السعة المطلوبة بالثواني في الساعة الواحدة
            $interval = $request->interval_minutes;
            $duration = $request->duration;
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
                    ->where('is_active', true)
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

            // حفظ الملف
            $file = $request->file('file');
            $path = $file->store('ads', 'public');
            $sizeInMB = $file->getSize() / 1048576;

            // تحديد الحالة الأولية
            $initialStatus = $request->hasFile('receipt') ? 'Pending' : 'waiting_payment';

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
                'is_active'         => true,
            ]);

            // تسجيل إيصال الدفع إن وجد
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store('receipts', 'public');
                \App\Models\FinancialLedger::create([
                    'advertisement_id' => $ad->ad_id,
                    'user_id'          => $ad->advertiser_id ?? $request->user()->user_id,
                    'transaction_type' => 'payment_pending',
                    'amount'           => $ad->total_cost,
                    'status'           => 'pending',
                    'notes'            => "إيصال دفع مرفق عند إنشاء الإعلان: {$ad->title}",
                    'receipt_path'     => '/storage/' . $receiptPath,
                ]);
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
}
