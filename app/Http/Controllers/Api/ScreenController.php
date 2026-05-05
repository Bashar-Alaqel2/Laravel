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
        ]);

        // إنشاء الشاشة، وربط المالك بالمستخدم الذي أرسل الطلب (عن طريق التوكن)
        $screen = Screen::create([
            'owner_id'    => $request->user()->user_id, 
            'screen_name' => $request->screen_name,
            'type_id'     => $request->type_id,
            'street_id'   => $request->street_id,
            'status'      => 'Offline', // عند التسجيل تكون الشاشة غير متصلة حتى يفتحها نجم الدين من التطبيق
        ]);

        return response()->json([
            'message' => 'تم إضافة الشاشة بنجاح',
            'screen'  => $screen
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
    // (نجم الدين سيقوم ببرمجة التطبيق ليرسل طلب لهذا المسار كل دقيقة مثلاً)
    // ==========================================
    public function ping(Request $request, $id)
    {
        $screen = Screen::find($id);
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير موجودة'], 404);
        }

        // نجعل الشاشة Online ونسجل وقت الاتصال
        $screen->update([
            'status' => 'Online',
            'linked_at' => now(), // وقت آخر اتصال
        ]);

        return response()->json(['message' => 'الشاشة متصلة وتعمل بشكل سليم', 'status' => 'Online'], 200);
    }
}
