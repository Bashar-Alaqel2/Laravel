<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Advertisement;
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

        // الإدارة والسكرتارية يرون كل الإعلانات
        if ($user->can('manage_all') || $user->can('review_ads')) {
            $ads = Advertisement::where('is_deleted', false)->get();
            return response()->json($ads, 200);
        }

        // المعلن يرى إعلاناته فقط
        if ($user->can('view_own_reports')) {
            $ads = Advertisement::where('advertiser_id', $user->user_id)
                                ->where('is_deleted', false)
                                ->get();
            return response()->json($ads, 200);
        }

        return response()->json(['error' => 'ليس لديك صلاحية لرؤية الإعلانات.'], 403);
    }

    // ==========================================
    // 2. رفع إعلان جديد (Store)
    // ==========================================
    public function store(Request $request)
    {
        // التحقق من صلاحية "رفع الإعلانات"
        if (!$request->user()->can('create_campaigns')) {
            return response()->json(['error' => 'ليس لديك صلاحية لرفع الإعلانات.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:150',
            'category_id' => 'nullable|exists:categories,category_id',
            'duration'    => 'required|integer|min:5', // مدة الإعلان بالثواني
            'file'        => 'required|file|mimes:mp4,jpeg,png,gif|max:51200', // أقصى حجم 50 ميجا
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // حفظ الملف في مجلد storage/app/public/ads
        $file = $request->file('file');
        $path = $file->store('ads', 'public');
        $sizeInMB = $file->getSize() / 1048576; // تحويل البايت إلى ميجابايت

        $ad = Advertisement::create([
            'advertiser_id' => $request->user()->user_id,
            'category_id'   => $request->category_id,
            'title'         => $request->title,
            'file_path'     => $path,
            'duration'      => $request->duration,
            'file_size'     => round($sizeInMB, 2),
            'status'        => 'Pending', // حالة مبدئية: قيد الانتظار
        ]);

        return response()->json([
            'message' => 'تم رفع الإعلان بنجاح، وهو بانتظار موافقة الإدارة.',
            'ad'      => $ad
        ], 201);
    }

    // ==========================================
    // 3. الموافقة أو رفض الإعلان (Update Status)
    // ==========================================
    public function updateStatus(Request $request, $id)
    {
        // فقط السكرتير أو الآدمن يستطيع الموافقة
        if (!$request->user()->can('approve_ads') && !$request->user()->can('manage_all')) {
            return response()->json(['error' => 'ليس لديك صلاحية لاعتماد الإعلانات.'], 403);
        }

        $request->validate([
            'status' => 'required|in:Approved,Rejected',
            'reason' => 'required_if:status,Rejected|string|nullable' // مطلوب سبب في حال الرفض
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
            'message' => "تم تغيير حالة الإعلان إلى {$ad->status}.",
            'ad'      => $ad
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
