<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use App\Models\UserSession;
use App\Models\BankAccount;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    // ==========================================
    // 1. دالة إنشاء حساب جديد (Registration)
    // ==========================================
    public function register(Request $request)
    {
        // 1. الرسائل المخصصة باللغة العربية
        $messages = [
            'full_name.required' => 'عفواً، حقل الاسم بالكامل مطلوب.',
            'full_name.max'      => 'الاسم طويل جداً، يجب ألا يتجاوز 100 حرف.',
            'email.required'     => 'يرجى إدخال البريد الإلكتروني.',
            'email.email'        => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique'       => 'عفواً، هذا البريد الإلكتروني مسجل لدينا مسبقاً.',
            'phone.required'     => 'يرجى إدخال رقم الجوال.',
            'phone.unique'       => 'رقم الجوال هذا مستخدم بالفعل في حساب آخر.',
            'password.required'  => 'كلمة المرور مطلوبة.',
            'password.min'       => 'كلمة المرور ضعيفة، يجب أن تتكون من 6 أحرف على الأقل.',
        ];

        // 2. التأكد من أن البيانات التي أرسلها الـ Frontend صحيحة وكاملة
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:100',
            'email'     => 'required|string|email|max:150|unique:users',
            'phone'     => 'required|string|max:20|unique:users',
            'location'  => 'nullable|string|max:255',
            'password'  => 'required|string|min:6',
        ], $messages); // <-- قمنا بتمرير مصفوفة الرسائل هنا

        if ($validator->fails()) {
            // الآن Postman وتطبيقات زملائك ستستلم الخطأ بالعربي
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 3. البحث عن رقم صلاحية "معلن" لإعطائها للمستخدم الجديد تلقائياً
        $advertiserRole = Role::where('role_name', 'Advertiser')->first();

        // 4. إدخال البيانات في قاعدة البيانات
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
        }        // 4. توليد مفتاح الأمان (Token) 
        $deviceName = $request->device_name ?? 'Unknown Device';
        $tokenResult = $user->createToken($deviceName);
        $token = $tokenResult->plainTextToken;
        $tokenId = $tokenResult->accessToken->id;

        // 5. حفظ بيانات الجلسة في جدول user_sessions الذي صممه زيد
        // نستخدم ID التوكن الفعلي كـ device_id لضمان مطابقة فريدة ومثالية 100% بين الجدولين
        $deviceId = 'token_' . $tokenId;
        
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
            'role'    => $user->role?->role_name ?? 'بدون صلاحية',
            'token'   => $token 
        ], 200);
    }
    // ==========================================
    // 3. دالة تسجيل الخروج (Logout)
    // ==========================================
    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $user->currentAccessToken();
        
        if ($token) {
            // حذف الجلسة المطابقة تماماً برقم التوكن الفريد من جدول user_sessions
            UserSession::where('user_id', $user->user_id)
                       ->where('device_id', 'token_' . $token->id)
                       ->delete();
                       
            // حذف التوكن الفعلي
            $token->delete();
        }

        return response()->json([
            'message' => 'تم تسجيل الخروج بنجاح.'
        ], 200);
    }

    // ==========================================
    // 4. إدارة المستخدمين (للمدير العام)
    // ==========================================

    // جلب كل المستخدمين
    public function getAllUsers(Request $request)
    {
        // يمكننا إضافة فلاتر حسب الدور هنا مستقبلاً
        $users = User::with('role')->orderBy('created_at', 'desc')->get();
        return response()->json($users, 200);
    }

    // إضافة مستخدم جديد (من قبل المدير)
    public function storeUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:100',
            'email'     => 'required|string|email|max:150|unique:users',
            'phone'     => 'required|string|max:20|unique:users',
            'role_id'   => 'required|exists:roles,role_id',
            'password'  => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'role_id'       => $request->role_id,
            'full_name'     => $request->full_name,
            'email'         => $request->email,
            'phone'         => $request->phone,
            'location'      => $request->location ?? 'غير محدد',
            'password_hash' => Hash::make($request->password),
            'account_status'=> 'Active'
        ]);

        $role = Role::find($request->role_id);
        if ($role && $role->role_name === 'ScreenOwner') {
            if ($request->bank_name && $request->account_number) {
                BankAccount::create([
                    'user_id' => $user->user_id,
                    'bank_name' => $request->bank_name,
                    'account_name' => $request->account_name ?? $request->full_name,
                    'account_number' => $request->account_number,
                ]);
            }
        }

        return response()->json(['message' => 'تم إضافة المستخدم بنجاح', 'data' => $user->load('role')], 201);
    }

    // حذف مستخدم
    public function destroyUser($id)
    {
        $user = User::findOrFail($id);
        
        // منع حذف النفس
        if ($user->user_id === auth()->id()) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الشخصي!'], 403);
        }

        $user->delete();
        return response()->json(['message' => 'تم حذف المستخدم بنجاح'], 200);
    }

    // تحديث رتبة/دور المستخدم
    public function updateUserRole(Request $request, $id)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,role_id'
        ]);

        $user = User::findOrFail($id);
        $user->role_id = $request->role_id;
        $user->save();

        $role = Role::find($request->role_id);
        if ($role && $role->role_name === 'ScreenOwner') {
            if ($request->bank_name && $request->account_number) {
                BankAccount::updateOrCreate(
                    ['user_id' => $user->user_id],
                    [
                        'bank_name' => $request->bank_name,
                        'account_name' => $request->account_name ?? $user->full_name,
                        'account_number' => $request->account_number,
                    ]
                );
            }
        }

        return response()->json([
            'message' => 'تم تحديث رتبة المستخدم بنجاح',
            'user' => $user->load('role')
        ], 200);
    }

    // ==========================================
    // إدارة الجلسات (Sessions Management)
    // ==========================================

    // جلب كافة الأجهزة (الجلسات) النشطة للمستخدم الحالي (وللإدارة: كل الجلسات لكل المستخدمين)
    public function getSessions(Request $request)
    {
        $currentToken = $request->user()->currentAccessToken();
        $user = $request->user();

        if ($user->role?->role_name === 'SuperAdmin') {
            $tokens = \Laravel\Sanctum\PersonalAccessToken::with('tokenable')->orderBy('last_used_at', 'desc')->get()->map(function ($token) use ($currentToken) {
                $tokenUser = $token->tokenable;
                return [
                    'id' => $token->id,
                    'device_name' => $token->name,
                    'user_name' => $tokenUser ? $tokenUser->full_name : 'مستخدم محذوف',
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'is_current' => $token->id === $currentToken->id,
                ];
            });
        } else {
            $tokens = $user->tokens()->orderBy('last_used_at', 'desc')->get()->map(function ($token) use ($currentToken, $user) {
                return [
                    'id' => $token->id,
                    'device_name' => $token->name,
                    'user_name' => $user->full_name,
                    'last_used_at' => $token->last_used_at,
                    'created_at' => $token->created_at,
                    'is_current' => $token->id === $currentToken->id,
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => $tokens
        ], 200);
    }    // تسجيل الخروج من كافة الأجهزة الأخرى للمستخدم الحالي (أو كافة الأجهزة في النظام للمدير العام)
    public function revokeOtherSessions(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken->id;
        
        if ($user->role?->role_name === 'SuperAdmin') {
            // حذف الجلسات من جدول user_sessions لكافة المستخدمين والأجهزة الأخرى برقم التوكن الفريد
            UserSession::where('device_id', '!=', 'token_' . $currentTokenId)->delete();

            // حذف التوكنات للأجهزة الأخرى في النظام بالكامل
            \Laravel\Sanctum\PersonalAccessToken::where('id', '!=', $currentTokenId)->delete();
        } else {
            // حذف الجلسات من جدول user_sessions للأجهزة الأخرى الخاصة بالمستخدم الحالي فقط برقم التوكن الفريد
            UserSession::where('user_id', $user->user_id)
                       ->where('device_id', '!=', 'token_' . $currentTokenId)
                       ->delete();

            // حذف التوكنات للأجهزة الأخرى الخاصة بالمستخدم الحالي
            $user->tokens()->where('id', '!=', $currentTokenId)->delete();
        }
        
        return response()->json([
            'success' => true, 
            'message' => 'تم تسجيل الخروج من جميع الأجهزة الأخرى بنجاح.'
        ], 200);
    }

    // إنهاء جلسة معينة (طرد جهاز محدد)
    public function revokeSession(Request $request, $tokenId)
    {
        $currentToken = $request->user()->currentAccessToken();
        $currentTokenId = $currentToken->id;
        
        if ($currentTokenId == $tokenId) {
            return response()->json([
                'success' => false, 
                'message' => 'لا يمكنك إنهاء الجلسة الحالية من هنا، استخدم زر تسجيل الخروج الرئيسي.'
            ], 400);
        }

        // البحث عن التوكن لمعرفة مالكه واسم الجهاز المرتبط به
        $token = \Laravel\Sanctum\PersonalAccessToken::find($tokenId);
        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'الجلسة المطلوبة غير موجودة أو تم إنهاؤها مسبقاً.'
            ], 404);
        }

        // التحقق من الصلاحيات: إذا لم يكن SuperAdmin، فيجب أن يملك الجلسة بنفسه
        if ($request->user()->role?->role_name !== 'SuperAdmin' && $token->tokenable_id !== $request->user()->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'غير مصرح لك بإنهاء جلسات هذا المستخدم.'
            ], 403);
        }

        // حذف الجلسة المقابلة تماماً برقم التوكن الفريد من جدول user_sessions
        UserSession::where('user_id', $token->tokenable_id)
                   ->where('device_id', 'token_' . $token->id)
                   ->delete();

        // حذف التوكن الفعلي من جدول personal_access_tokens
        $token->delete();
        
        return response()->json([
            'success' => true, 
            'message' => 'تم إنهاء الجلسة للجهاز المحدد بنجاح.'
        ], 200);
    }


    // ==========================================
    // تحديث البيانات الشخصية
    // ==========================================
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:100',
            'email'     => 'required|string|email|max:150|unique:users,email,' . $user->user_id . ',user_id',
            'phone'     => 'required|string|max:20|unique:users,phone,' . $user->user_id . ',user_id',
        ], [
            'full_name.required' => 'حقل الاسم بالكامل مطلوب.',
            'email.required'     => 'يرجى إدخال البريد الإلكتروني.',
            'email.email'        => 'صيغة البريد الإلكتروني غير صحيحة.',
            'email.unique'       => 'هذا البريد الإلكتروني مسجل مسبقاً لمستخدم آخر.',
            'phone.required'     => 'يرجى إدخال رقم الهاتف.',
            'phone.unique'       => 'رقم الهاتف مسجل مسبقاً لمستخدم آخر.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->update([
            'full_name' => $request->full_name,
            'email'     => $request->email,
            'phone'     => $request->phone,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث البيانات الشخصية بنجاح.',
            'user'    => $user->load('role')
        ], 200);
    }

    // ==========================================
    // تغيير كلمة المرور
    // ==========================================
    public function changePassword(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'new_password'     => 'required|string|min:6|confirmed',
        ], [
            'current_password.required' => 'كلمة المرور الحالية مطلوبة.',
            'new_password.required'     => 'كلمة المرور الجديدة مطلوبة.',
            'new_password.min'          => 'يجب ألا تقل كلمة المرور الجديدة عن 6 أحرف.',
            'new_password.confirmed'    => 'تأكيد كلمة المرور الجديدة غير متطابق.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (!Hash::check($request->current_password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'كلمة المرور الحالية غير صحيحة.'
            ], 400);
        }

        $user->update([
            'password_hash' => Hash::make($request->new_password)
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تغيير كلمة المرور بنجاح.'
        ], 200);
    }
}