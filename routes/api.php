<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\ScreenController;

// مسارات مفتوحة (لا تحتاج تسجيل دخول)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/test-s3', function () {
    try {
        $disk = \Illuminate\Support\Facades\Storage::disk('s3');
        $disk->put('test-vercel.txt', 'Hello from Vercel!');
        return response()->json([
            'success' => true, 
            'url' => $disk->url('test-vercel.txt'),
            'env' => [
                'bucket' => env('AWS_BUCKET'),
                'region' => env('AWS_DEFAULT_REGION'),
                'has_key' => !empty(env('AWS_ACCESS_KEY_ID')),
                'has_secret' => !empty(env('AWS_SECRET_ACCESS_KEY')),
                'path_style' => env('AWS_USE_PATH_STYLE_ENDPOINT'),
                'path_style_type' => gettype(env('AWS_USE_PATH_STYLE_ENDPOINT')),
            ]
        ]);
    } catch (\Exception $e) {
        $prev = $e->getPrevious() ? ' | Prev: ' . $e->getPrevious()->getMessage() : '';
        return response()->json([
            'success' => false, 
            'error' => $e->getMessage() . $prev,
            'debug_config' => config('filesystems.disks.s3.use_path_style_endpoint')
        ]);
    }
});
Route::get('/login', function() {
    return response()->json(['success' => false, 'message' => 'Unauthenticated or Redirected.'], 401);
})->name('login');

// مسار ربط الشاشات الفيزيائية
Route::post('/screens/link', [ScreenController::class, 'linkScreen']);
Route::post('/screens/ping', [ScreenController::class, 'ping']);
Route::get('/screens/check', [ScreenController::class, 'check']);
Route::post('/screens/generate-id', [ScreenController::class, 'generateId']);
Route::get('/playlist', [App\Http\Controllers\Api\PlaylistController::class, 'getPlaylist']);
Route::get('/settings', function() { return response()->json(['success' => true, 'data' => []]); });

Route::post('/payments/stripe/webhook', [App\Http\Controllers\Api\StripePaymentController::class, 'handleWebhook']);
Route::get('/tickers', function() { return response()->json(['success' => true, 'data' => null]); }); // مسار نبض الشاشة المفتوح للشاشات المربوطة
Route::get('/ads-test', function() {
    return response()->json([
        'success' => true,
        'data' => \App\Models\Advertisement::with(['advertiser', 'screens.street.region.governorate', 'category'])->where('is_deleted', false)->get()
    ]);
});

// مسارات محمية (يجب إرسال التوكن للوصول إليها)
Route::middleware('auth:sanctum')->group(function () {
    
    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);

    // === تحديث الملف الشخصي وتغيير كلمة المرور ===
    Route::put('/profile/update', [AuthController::class, 'updateProfile']);
    Route::put('/profile/change-password', [AuthController::class, 'changePassword']);

    // === الدفع الإلكتروني Stripe ===
    Route::post('/payments/stripe/create-intent', [App\Http\Controllers\Api\StripePaymentController::class, 'createPaymentIntent']);
    Route::post('/payments/stripe/confirm', [App\Http\Controllers\Api\StripePaymentController::class, 'confirmPayment']);
    
    // === إدارة الجلسات ===
    Route::get('/sessions', [AuthController::class, 'getSessions']);
    Route::delete('/sessions/others', [AuthController::class, 'revokeOtherSessions']);
    Route::delete('/sessions/{id}', [AuthController::class, 'revokeSession']);
    
    // === إدارة المستخدمين ===
    Route::get('/users', [AuthController::class, 'getAllUsers']);
    Route::post('/users', [AuthController::class, 'storeUser']);
    Route::put('/users/{id}/role', [AuthController::class, 'updateUserRole']);
    Route::delete('/users/{id}', [AuthController::class, 'destroyUser']);
    
    // === مسارات الشاشات (للوحة التحكم ولتطبيق Flutter) ===
    Route::post('/screens/command', [App\Http\Controllers\Api\ScreenController::class, 'sendCommand']);
    Route::get('/screens/{id}/availability', [App\Http\Controllers\Api\ScreenController::class, 'getAvailability']);
    Route::apiResource('screens', App\Http\Controllers\Api\ScreenController::class); // تنشئ مسارات: index, store, show, update, destroy
    // === مسارات الإعلانات ===
    Route::get('/ads', [App\Http\Controllers\Api\AdController::class, 'index']);
    Route::post('/ads', [App\Http\Controllers\Api\AdController::class, 'store']);
    Route::post('/ads/calculate-cost', [App\Http\Controllers\Api\AdController::class, 'calculateCost']);
    Route::put('/ads/{id}/status', [App\Http\Controllers\Api\AdController::class, 'updateStatus']);
    Route::delete('/ads/{id}', [App\Http\Controllers\Api\AdController::class, 'destroy']);

    // === مسارات أوقات الذروة والتسعير (Peak Hours & Pricing) ===
    Route::apiResource('screen-pricing-slots', App\Http\Controllers\Api\ScreenPricingSlotController::class);
    
    // === مسارات باقات التكرار (Frequency Packages) ===
    Route::apiResource('frequency-packages', App\Http\Controllers\Api\FrequencyPackageController::class);
    
    // === مسارات خصومات المدد الإعلانية (Duration Discounts) ===
    Route::apiResource('duration-discounts', App\Http\Controllers\Api\DurationDiscountController::class);
    
    // === مسارات لوحة التحكم (Dashboard) ===
    Route::get('/dashboard/overview', [App\Http\Controllers\Api\DashboardController::class, 'getOverview']);
    Route::get('/owner/dashboard', [App\Http\Controllers\Api\DashboardController::class, 'getOwnerOverview']);
    
    // === مسارات المعلن (Advertiser) ===
    Route::get('/advertiser/dashboard', [App\Http\Controllers\Api\AdvertiserController::class, 'getDashboard']);
    Route::get('/advertiser/financials', [App\Http\Controllers\Api\AdvertiserController::class, 'getFinancials']);
    
    // === مسارات الإشعارات (Notifications) ===
    Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
    Route::put('/notifications/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications/{id}', [App\Http\Controllers\Api\NotificationController::class, 'destroy']);
    
    // === مسارات القوائم المنسدلة (Lookups) ===
    // جلب البيانات (GET)
    Route::get('/lookups/screen-types', [App\Http\Controllers\Api\LookupController::class, 'getScreenTypes']);
    Route::get('/lookups/governorates', [App\Http\Controllers\Api\LookupController::class, 'getGovernorates']);
    Route::get('/lookups/governorates/{gov_id}/regions', [App\Http\Controllers\Api\LookupController::class, 'getRegions']);
    Route::get('/lookups/all-regions', [App\Http\Controllers\Api\LookupController::class, 'getAllRegions']);
    Route::get('/lookups/regions/{region_id}/streets', [App\Http\Controllers\Api\LookupController::class, 'getStreets']);
    Route::get('/lookups/streets', [App\Http\Controllers\Api\LookupController::class, 'getAllStreets']); // جلب كل الشوارع مباشرة
    Route::get('/lookups/categories', [App\Http\Controllers\Api\LookupController::class, 'getCategories']);
    Route::get('/lookups/users-by-role/{roleName}', [App\Http\Controllers\Api\LookupController::class, 'getUsersByRole']); // جلب المستخدمين حسب الدور
    Route::get('/lookups/roles', [App\Http\Controllers\Api\LookupController::class, 'getRoles']); // جلب كل الأدوار
    Route::post('/lookups/roles', [App\Http\Controllers\Api\LookupController::class, 'storeRole']); // إضافة دور جديد
    Route::put('/lookups/roles/{id}', [App\Http\Controllers\Api\LookupController::class, 'updateRole']); // تعديل دور
    Route::delete('/lookups/roles/{id}', [App\Http\Controllers\Api\LookupController::class, 'destroyRole']); // حذف دور
    
    // إضافة البيانات من قبل المدير العام (POST)
    Route::post('/lookups/screen-types', [App\Http\Controllers\Api\LookupController::class, 'storeScreenType']);
    Route::post('/lookups/governorates', [App\Http\Controllers\Api\LookupController::class, 'storeGovernorate']);
    Route::post('/lookups/regions', [App\Http\Controllers\Api\LookupController::class, 'storeRegion']);
    Route::post('/lookups/streets', [App\Http\Controllers\Api\LookupController::class, 'storeStreet']);
    Route::post('/lookups/full-location', [App\Http\Controllers\Api\LookupController::class, 'storeFullLocation']);
    Route::post('/lookups/categories', [App\Http\Controllers\Api\LookupController::class, 'storeCategory']);
    
    // تعديل وحذف البيانات من قبل المدير العام (PUT & DELETE)
    Route::put('/lookups/governorates/{id}', [App\Http\Controllers\Api\LookupController::class, 'updateGovernorate']);
    Route::delete('/lookups/governorates/{id}', [App\Http\Controllers\Api\LookupController::class, 'destroyGovernorate']);
    
    Route::put('/lookups/regions/{id}', [App\Http\Controllers\Api\LookupController::class, 'updateRegion']);
    Route::delete('/lookups/regions/{id}', [App\Http\Controllers\Api\LookupController::class, 'destroyRegion']);
    
    Route::put('/lookups/streets/{id}', [App\Http\Controllers\Api\LookupController::class, 'updateStreet']);
    Route::delete('/lookups/streets/{id}', [App\Http\Controllers\Api\LookupController::class, 'destroyStreet']);
    
    Route::put('/lookups/categories/{id}', [App\Http\Controllers\Api\LookupController::class, 'updateCategory']);
    Route::delete('/lookups/categories/{id}', [App\Http\Controllers\Api\LookupController::class, 'destroyCategory']);
    
    // === مسارات النظام المالي ===
    Route::get('/financial/ledger', [App\Http\Controllers\Api\FinancialController::class, 'getLedger']);
    Route::post('/financial/payments', [App\Http\Controllers\Api\FinancialController::class, 'recordPayment']);
    Route::post('/financial/approve-payment/{id}', [App\Http\Controllers\Api\FinancialController::class, 'approvePayment']);
    Route::get('/financial/my-earnings', [App\Http\Controllers\Api\FinancialController::class, 'getOwnerEarnings']);
    
    Route::apiResource('payment-methods', App\Http\Controllers\Api\PaymentMethodController::class);
    Route::post('/payments/stripe/create-intent', [App\Http\Controllers\Api\StripePaymentController::class, 'createPaymentIntent']);
    Route::post('/payments/manual', [App\Http\Controllers\Api\ManualPaymentController::class, 'store']);
    
});