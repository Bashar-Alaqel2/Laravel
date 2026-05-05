<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
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
            'location'  => 'required|string',
            'password'  => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. البحث عن رقم صلاحية "معلن" لإعطائها للمستخدم الجديد تلقائياً
        // (تأكد أنك قمت بإضافة صلاحية اسمها Advertiser يدوياً في الداتا بيز)
        $advertiserRole = Role::where('role_name', 'Advertiser')->first();

        // 3. إدخال البيانات في قاعدة البيانات
        $user = User::create([
            'role_id'       => $advertiserRole ? $advertiserRole->role_id : null,
            'full_name'     => $request->full_name,
            'email'         => $request->email,
            'phone'         => $request->phone,
            'location'      => $request->location,
            'password_hash' => Hash::make($request->password), // تشفير كلمة المرور
        ]);

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح، يمكنك الآن تسجيل الدخول.',
            'user'    => $user
        ], 201);
    }


    // ==========================================
    // 2. دالة تسجيل الدخول (Login بالايميل أو الجوال)
    // ==========================================
    public function login(Request $request)
    {
        // زميلك سيرسل حقل اسمه login_id (قد يكون إيميل أو جوال) وحقل password
        $request->validate([
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        // 1. معرفة هل المدخل هو إيميل أم رقم جوال؟
        $loginField = filter_var($request->login_id, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // 2. البحث عن المستخدم في قاعدة البيانات
        $user = User::where($loginField, $request->login_id)->first();

        // 3. التحقق من وجود المستخدم وصحة كلمة المرور
        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json(['message' => 'بيانات الدخول غير صحيحة.'], 401);
        }

        // 4. التحقق من أن الحساب غير موقوف
        if ($user->account_status !== 'Active') {
            return response()->json(['message' => 'هذا الحساب موقوف، يرجى التواصل مع الإدارة.'], 403);
        }

        // 5. توليد مفتاح الأمان (Token) للتطبيق ولوحة التحكم
        $token = $user->createToken('SuqAppToken')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح.',
            'user'    => $user,
            'role'    => $user->role->role_name ?? 'بدون صلاحية',
            'token'   => $token // هذا التوكن سيحتفظ به زميلك ليستخدمه في الطلبات القادمة
        ], 200);
    }
}