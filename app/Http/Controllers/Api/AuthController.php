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
        $advertiserRole = Role::find(Role::ADVERTISER);
        if (!$advertiserRole) {
            $advertiserRole = Role::create(['role_id' => Role::ADVERTISER, 'role_name' => 'Advertiser']);
        }

        // 4. إدخال البيانات في قاعدة البيانات
        $user = User::create([
            'role_id'       => $advertiserRole->role_id,
            'full_name'     => $request->full_name,
            'email'         => $request->email,
            'phone'         => $request->phone,
            'location'      => $request->location,
            'password_hash' => Hash::make($request->password), // تشفير كلمة المرور
            'account_status'=> 'Active'
        ]);

        // إرسال إشعار للمديرين
        \Illuminate\Support\Facades\Cache::forget('lookup_users_role_advertiser');

        $admins = User::whereHas('role', function($q) {
            $q->whereIn('role_id', [\App\Models\Role::ADMIN, \App\Models\Role::SECRETARY, \App\Models\Role::SUPER_ADMIN]);
        })->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->user_id,
                'title' => json_encode(['key' => 'notif_title_new_user']),
                'message' => json_encode(['key' => 'notif_msg_new_user', 'args' => ['name' => $user->full_name]]),
                'is_read' => false,
            ]);
        }

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
        if ($role && $role->role_id === \App\Models\Role::SCREEN_OWNER) {
            if ($request->bank_name && $request->account_number) {
                BankAccount::create([
                    'user_id' => $user->user_id,
                    'bank_name' => $request->bank_name,
                    'account_name' => $request->account_name ?? $request->full_name,
                    'account_number' => $request->account_number,
                ]);
            }
        }

        if ($role) {
            \Illuminate\Support\Facades\Cache::forget('lookup_users_role_' . strtolower($role->role_name));
        }

        return response()->json(['message' => 'تم إضافة المستخدم بنجاح', 'data' => $user->load('role')], 201);
    }

    // حذف مستخدم
    public function destroyUser($id)
    {
        // استخدام withTrashed للسماح بالوصول للمستخدمين الذين تم حذفهم لينة مسبقاً
        $user = User::withTrashed()->findOrFail($id);
        
        // منع حذف النفس
        if ($user->user_id === auth()->id()) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الشخصي!'], 403);
        }

        // التحقق من وجود نشاط أو بيانات مرتبطة بالمستخدم
        $hasActivity = $user->screens()->count() > 0 ||
                       $user->linkedScreens()->count() > 0 ||
                       $user->advertisements()->count() > 0 ||
                       \App\Models\FinancialLedger::where('user_id', $user->user_id)->count() > 0;

        if (!$hasActivity) {
            // حذف نهائي (Hard Delete) إذا لم يكن لديه نشاط
            $roleCacheKey = $user->role ? 'lookup_users_role_' . strtolower($user->role->role_name) : null;
            $user->forceDelete();
            if ($roleCacheKey) \Illuminate\Support\Facades\Cache::forget($roleCacheKey);
            return response()->json(['message' => 'تم حذف المستخدم نهائياً من قاعدة البيانات نظراً لعدم وجود أي نشاط مرتبط به'], 200);
        } else {
            // إذا كان المستخدم محذوف لينة مسبقاً فلا داعي لحذفه مرة أخرى
            if ($user->trashed()) {
                return response()->json(['message' => 'هذا المستخدم محذوف مسبقاً (حذف آمن) ولا يمكن حذفه نهائياً لوجود بيانات مرتبطة به'], 200);
            }
            // حذف آمن (Soft Delete) لوجود نشاط
            $user->delete();
            if ($user->role) \Illuminate\Support\Facades\Cache::forget('lookup_users_role_' . strtolower($user->role->role_name));
            return response()->json(['message' => 'تم حذف المستخدم (حذف آمن) للحفاظ على بياناته المالية والإعلانية المرتبطة'], 200);
        }
    }

    // تعديل بيانات المستخدم
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'full_name' => 'required|string|max:100',
            'email'     => 'required|string|email|max:150|unique:users,email,' . $id . ',user_id',
            'phone'     => 'nullable|string|max:20|unique:users,phone,' . $id . ',user_id',
            'location'  => 'nullable|string|max:100',
            'password'  => 'nullable|string|min:6',
            'role_id'   => 'nullable|exists:roles,role_id'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $data = $request->only(['full_name', 'email', 'phone', 'location']);
        if ($request->filled('password')) {
            $data['password_hash'] = Hash::make($request->password);
        }

        $roleChanged = false;
        if ($request->filled('role_id') && $user->role_id != $request->role_id) {
            $oldRole = $user->role;
            if ($oldRole) {
                \Illuminate\Support\Facades\Cache::forget('lookup_users_role_' . strtolower($oldRole->role_name));
            }
            
            $data['role_id'] = $request->role_id;
            $roleChanged = true;
            
            $newRole = Role::find($request->role_id);
            if ($newRole) {
                \Illuminate\Support\Facades\Cache::forget('lookup_users_role_' . strtolower($newRole->role_name));
            }
        }

        $user->update($data);

        if ($roleChanged) {
            $user->tokens()->delete();
            \App\Models\UserSession::where('user_id', $user->user_id)->delete();
        }

        return response()->json([
            'message' => 'تم تحديث بيانات المستخدم بنجاح' . ($roleChanged ? ' وإنهاء جلساته لتفعيل الصلاحية الجديدة' : ''), 
            'data' => $user->load('role')
        ], 200);
    }

    // تعديل حالة حساب المستخدم (تفعيل/إيقاف)
    public function updateUserStatus(Request $request, $id)
    {
        $request->validate([
            'account_status' => 'required|in:Active,Suspended'
        ]);

        $user = User::findOrFail($id);
        $user->account_status = $request->account_status;
        $user->save();

        return response()->json(['message' => 'تم تحديث حالة الحساب بنجاح', 'data' => $user], 200);
    }

    // تعديل دور المستخدم (صلاحيته)
    public function updateUserRole(Request $request, $id)
    {
        $request->validate([
            'role_id' => 'required|exists:roles,role_id'
        ]);

        $user = User::findOrFail($id);
        
        $oldRole = $user->role;
        if ($oldRole) {
            \Illuminate\Support\Facades\Cache::forget('lookup_users_role_' . strtolower($oldRole->role_name));
        }

        $user->role_id = $request->role_id;
        $user->save();

        $role = Role::find($request->role_id);
        if ($role) {
            \Illuminate\Support\Facades\Cache::forget('lookup_users_role_' . strtolower($role->role_name));
        }
        if ($role && $role->role_id === \App\Models\Role::SCREEN_OWNER) {
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

        // إنهاء جميع الجلسات النشطة للمستخدم ليتم إجباره على تسجيل الدخول بالصلاحية الجديدة
        $user->tokens()->delete();
        \App\Models\UserSession::where('user_id', $user->user_id)->delete();

        return response()->json([
            'message' => 'تم تحديث رتبة المستخدم بنجاح وإنهاء جلساته لتفعيل الصلاحية الجديدة',
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

        if ($user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
            $sessions = UserSession::with('user')->orderBy('last_active', 'desc')->get()->map(function ($session) use ($currentToken) {
                $tokenId = str_replace('token_', '', $session->device_id);
                return [
                    'id' => $tokenId,
                    'device_name' => $session->device_name,
                    'ip_address' => $session->ip_address,
                    'user_name' => $session->user ? $session->user->full_name : 'مستخدم محذوف',
                    'last_used_at' => $session->last_active,
                    'created_at' => $session->created_at,
                    'is_current' => (int)$tokenId === (int)$currentToken->id,
                ];
            });
        } else {
            $sessions = UserSession::where('user_id', $user->user_id)->orderBy('last_active', 'desc')->get()->map(function ($session) use ($currentToken, $user) {
                $tokenId = str_replace('token_', '', $session->device_id);
                return [
                    'id' => $tokenId,
                    'device_name' => $session->device_name,
                    'ip_address' => $session->ip_address,
                    'user_name' => $user->full_name,
                    'last_used_at' => $session->last_active,
                    'created_at' => $session->created_at,
                    'is_current' => (int)$tokenId === (int)$currentToken->id,
                ];
            });
        }

        return response()->json([
            'success' => true,
            'data' => $sessions
        ], 200);
    }    // تسجيل الخروج من كافة الأجهزة الأخرى للمستخدم الحالي (أو كافة الأجهزة في النظام للمدير العام)
    public function revokeOtherSessions(Request $request)
    {
        $user = $request->user();
        $currentToken = $user->currentAccessToken();
        $currentTokenId = $currentToken->id;
        
        if ($user->hasRole(\App\Models\Role::SUPER_ADMIN)) {
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
        if (!$request->user()->hasRole(\App\Models\Role::SUPER_ADMIN) && $token->tokenable_id !== $request->user()->user_id) {
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
