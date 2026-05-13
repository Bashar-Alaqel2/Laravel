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
    // 2. رفع إعلان جديد (Store)
    // ==========================================
    public function store(Request $request)
    {
        // التحقق من صلاحية "رفع الإعلانات"
        if (!$request->user()->can('create_campaigns')) {
            return response()->json(['success' => false, 'message' => 'ليس لديك صلاحية لرفع الإعلانات.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'           => 'required|string|max:150',
            'advertiser_id'   => 'nullable|exists:users,user_id', // إذا لم يرسل، نستخدم الحالي
            'category_id'     => 'nullable|exists:categories,category_id',
            'duration'        => 'required|integer|min:1', 
            'file'            => 'required|file|mimes:mp4,mov,avi,jpeg,png,jpg|max:51200', 
            'start_date'      => 'required|date',
            'end_date'        => 'required|date|after_or_equal:start_date',
            'daily_frequency' => 'required|integer|min:1',
            'total_cost'      => 'required|numeric',
            'package_name'    => 'nullable|string',
            'screen_ids'      => 'required|array',
            'screen_ids.*'    => 'exists:screens,screen_id',
            'receipt'         => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        try {
            // حفظ الملف
            $file = $request->file('file');
            $path = $file->store('ads', 'public');
            $sizeInMB = $file->getSize() / 1048576;

            $ad = Advertisement::create([
                'advertiser_id'   => $request->advertiser_id ?? $request->user()->user_id,
                'category_id'     => $request->category_id,
                'title'           => $request->title,
                'file_path'       => '/storage/' . $path,
                'duration'        => $request->duration,
                'file_size'       => round($sizeInMB, 2),
                'start_date'      => $request->start_date,
                'end_date'        => $request->end_date,
                'daily_frequency' => $request->daily_frequency,
                'total_cost'      => $request->total_cost,
                'package_name'    => $request->package_name,
                'status'          => 'Pending',
            ]);

            // ربط الشاشات
            $ad->screens()->sync($request->screen_ids);

            // إذا أرفق صورة إيصال الدفع، نسجلها في النظام المالي كـ "قيد الانتظار"
            if ($request->hasFile('receipt')) {
                $receiptPath = $request->file('receipt')->store('receipts', 'public');
                FinancialLedger::create([
                    'advertisement_id' => $ad->ad_id,
                    'user_id'          => $ad->advertiser_id,
                    'transaction_type' => 'payment_pending',
                    'amount'           => $ad->total_cost,
                    'status'           => 'pending',
                    'notes'            => "إيصال دفع مرفق عند إنشاء الإعلان: {$ad->title}",
                    'receipt_path'     => '/storage/' . $receiptPath,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم رفع الإعلان بنجاح. ' . ($request->hasFile('receipt') ? 'بانتظار مراجعة الإيصال وتأكيد الدفع.' : 'يرجى إتمام عملية الدفع لتفعيل الإعلان.'),
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
        $ad->save();

        return response()->json(['message' => 'تم حذف الإعلان بنجاح.'], 200);
    }
}
