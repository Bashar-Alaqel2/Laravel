<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\UserSession;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // ==========================================
    // 1. دالة إنشاء حساب جديد (Registration)
    // ==========================================
    public function register(Request $request)
    {
        // 1. التأكد من أن البيانات التي أرسلها الـ Frontend صحيحة وكاملة
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:100',
            'email'     => 'required|string|email|max:150|unique:users',
            'phone'     => 'required|string|max:20|unique:users',
            'location'  => 'nullable|string|max:255',
            'password'  => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. البحث عن رقم صلاحية "معلن" لإعطائها للمستخدم الجديد تلقائياً
        $advertiserRole = Role::where('role_name', 'Advertiser')->first();

        // 3. إدخال البيانات في قاعدة البيانات
        $user = User::create([
            'role_id'       => $advertiserRole ? $advertiserRole->role_id : null,
            'full_name'     => $request->full_name,
            'email'         => $request->email,
            'phone'         => $request->phone,
            'location'      => $request->location,
            'password_hash' => Hash::make($request->password), // تشفير كلمة المرور
            'account_status'=> 'Active'
        ]);

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح، يمكنك الآن تسجيل الدخول.',
            'user'    => $user
        ], 201);
    }


    // ==========================================
    // 2. دالة تسجيل الدخول (Login)
    // ==========================================
    public function login(Request $request)
    {
        // زميلك سيرسل (الايميل أو الجوال) في حقل اسمه login_id ، وكلمة المرور، بالإضافة لبيانات الجهاز
        $request->validate([
            'login_id'    => 'required|string',
            'password'    => 'required|string',
            'device_name' => 'nullable|string', // اسم الجهاز (مثل iPhone 13)
            'device_id'   => 'nullable|string', // معرف فريد للجهاز
        ]);

        // 1. تحديد ما إذا كان المدخل إيميل أم رقم جوال
        $loginField = filter_var($request->login_id, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // 2. البحث عن المستخدم في قاعدة البيانات
        $user = User::where($loginField, $request->login_id)->first();

        // 2. التحقق من وجود المستخدم وصحة كلمة المرور
        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
        }

        // 3. التحقق من أن الحساب غير موقوف
        if ($user->account_status !== 'Active') {
            return response()->json(['message' => 'هذا الحساب موقوف، يرجى التواصل مع الإدارة.'], 403);
        }

        // 4. توليد مفتاح الأمان (Token) 
        $deviceName = $request->device_name ?? 'Unknown Device';
        $token = $user->createToken($deviceName)->plainTextToken;

        // 5. حفظ بيانات الجلسة في جدول user_sessions الذي صممه زيد
        $deviceId = $request->device_id ?? uniqid('dev_');
        
        UserSession::updateOrCreate(
            ['user_id' => $user->user_id, 'device_id' => $deviceId],
            [
                'device_name' => $deviceName,
                'ip_address'  => $request->ip(),
                'last_active' => now(),
                'is_revoked'  => false
            ]
        );

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح.',
            'user'    => $user,
            'role'    => $user->role->role_name ?? 'بدون صلاحية',
            'token'   => $token 
        ], 200);
    }

    // ==========================================
    // 3. دالة تسجيل الخروج (Logout)
    // ==========================================
    public function logout(Request $request)
    {
        // 1. حذف التوكن الحالي حتى لا يمكن استخدامه مجدداً
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح.'
        ], 200);
    }
}