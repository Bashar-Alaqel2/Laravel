<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Screen;

class ScreenController extends Controller
{
    // ==========================================
    // 1. جلب جميع الشاشات (للوحة التحكم - React - زميلك علي)
    // ==========================================
    public function index()
    {
        // نجلب الشاشات مع بيانات المالك ونوع الشاشة والشارع المتواجدة فيه
        $screens = Screen::with(['owner', 'type', 'street.region.governorate'])->get();
        return response()->json($screens, 200);
    }

    // ==========================================
    // 2. إضافة شاشة جديدة (لوحة التحكم)
    // ==========================================
    public function store(Request $request)
    {
        // التحقق من صحة البيانات
        $request->validate([
            'screen_name' => 'required|string|max:100',
            'type_id'     => 'nullable|exists:screen_types,type_id',
            'street_id'   => 'nullable|exists:streets,street_id',
            'owner_id'    => 'nullable|exists:users,user_id',
            'linked_by'   => 'nullable|exists:users,user_id',
        ]);

        // توليد كود ربط عشوائي فريد من 6 أحرف (أرقام وحروف)
        $pairingCode = strtoupper(\Illuminate\Support\Str::random(6));

        // التأكد من عدم تكراره (نادرة الحدوث لكن للاحتياط)
        while (Screen::where('pairing_code', $pairingCode)->exists()) {
            $pairingCode = strtoupper(\Illuminate\Support\Str::random(6));
        }

        // إنشاء الشاشة
        $screen = Screen::create([
            'owner_id'    => $request->owner_id ?? $request->user()->user_id, 
            'screen_name' => $request->screen_name,
            'type_id'     => $request->type_id,
            'street_id'   => $request->street_id,
            'linked_by'   => $request->linked_by,
            'status'      => 'Offline', // عند التسجيل تكون الشاشة غير متصلة
            'pairing_code'=> $pairingCode,
        ]);

        return response()->json([
            'message' => 'تم إضافة الشاشة بنجاح',
            'screen'  => $screen,
            'pairing_code' => $pairingCode // إرجاع الكود ليعرض في لوحة التحكم
        ], 201);
    }

    // ==========================================
    // 3. عرض شاشة محددة
    // ==========================================
    public function show($id)
    {
        $screen = Screen::with(['owner', 'type', 'street'])->find($id);
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير موجودة'], 404);
        }

        return response()->json($screen, 200);
    }

    // ==========================================
    // 4. تعديل بيانات الشاشة
    // ==========================================
    public function update(Request $request, $id)
    {
        $screen = Screen::find($id);
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير موجودة'], 404);
        }

        $request->validate([
            'screen_name' => 'nullable|string|max:100',
            'type_id'     => 'nullable|exists:screen_types,type_id',
            'street_id'   => 'nullable|exists:streets,street_id',
            'status'      => 'nullable|in:Online,Offline,Maintenance'
        ]);

        // نقوم بتحديث البيانات التي تم إرسالها فقط
        $screen->update($request->only(['screen_name', 'type_id', 'street_id', 'status']));

        return response()->json([
            'message' => 'تم تعديل الشاشة بنجاح',
            'screen'  => $screen
        ], 200);
    }

    // ==========================================
    // 5. حذف الشاشة
    // ==========================================
    public function destroy($id)
    {
        $screen = Screen::find($id);
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير موجودة'], 404);
        }

        // بما أنك تستخدم (SoftDeletes)، فهذا سيخفي الشاشة ولن يحذفها نهائياً من الداتابيز
        $screen->delete(); 

        return response()->json(['message' => 'تم حذف الشاشة بنجاح'], 200);
    }

    // -----------------------------------------------------------------
    // دوال مخصصة لتطبيق الشاشة (Flutter - زميلك نجم الدين)
    // -----------------------------------------------------------------

    // ==========================================
    // 6. نبض الشاشة (Ping) لتحديث حالتها إلى "متصلة"
    // (يتم إرسال الـ mac_address للتعرف على الشاشة)
    // ==========================================
    public function ping(Request $request)
    {
        $request->validate([
            'mac_address' => 'required|string',
        ]);

        $screen = Screen::where('mac_address', $request->mac_address)->first();
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير مربوطة'], 404);
        }

        // نجعل الشاشة Online ونسجل وقت الاتصال
        $screen->update([
            'status' => 'Online',
            'linked_at' => now(), // وقت آخر نبض/اتصال
        ]);

        return response()->json([
            'success' => true,
            'status' => 'Online',
            'last_seen' => $screen->linked_at
        ], 200);
    }

    // ==========================================
    // 6. ربط الشاشة الفيزيائية بالنظام (من تطبيق التلفاز)
    // ==========================================
    public function linkScreen(Request $request)
    {
        $request->validate([
            'pairing_code' => 'required|string',
            'mac_address'  => 'required|string',
        ]);

        $screen = Screen::where('pairing_code', $request->pairing_code)->first();

        if (!$screen) {
            return response()->json([
                'success' => false,
                'message' => 'كود الربط غير صحيح أو منتهي الصلاحية'
            ], 404);
        }

        // إذا كانت الشاشة مربوطة مسبقاً بجهاز آخر
        if ($screen->mac_address && $screen->mac_address !== $request->mac_address) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الشاشة مربوطة مسبقاً بجهاز آخر'
            ], 400);
        }

        // تحديث بيانات الشاشة
        $screen->update([
            'mac_address' => $request->mac_address,
            'status' => 'Online', // تصبح أونلاين فور الربط
            'linked_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم الربط بنجاح',
            'data' => [
                'screen_id' => $screen->screen_id,
                'screen_name' => $screen->screen_name,
            ]
        ], 200);
    }
}
