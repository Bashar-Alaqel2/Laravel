<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\ScreenController;

// مسارات مفتوحة (لا تحتاج تسجيل دخول)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// مسار ربط الشاشات الفيزيائية
Route::post('/screens/link', [ScreenController::class, 'linkScreen']);
Route::post('/screens/ping', [ScreenController::class, 'ping']); // مسار نبض الشاشة المفتوح للشاشات المربوطة

// مسارات محمية (يجب إرسال التوكن للوصول إليها)
Route::middleware('auth:sanctum')->group(function () {
    
    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // === مسارات الشاشات (للوحة التحكم ولتطبيق Flutter) ===
    Route::apiResource('screens', ScreenController::class); // تنشئ مسارات: index, store, show, update, destroy
    
    // === مسارات الإعلانات ===
    Route::get('/ads', [App\Http\Controllers\Api\AdController::class, 'index']);
    Route::post('/ads', [App\Http\Controllers\Api\AdController::class, 'store']);
    Route::put('/ads/{id}/status', [App\Http\Controllers\Api\AdController::class, 'updateStatus']);
    Route::delete('/ads/{id}', [App\Http\Controllers\Api\AdController::class, 'destroy']);
    
    // === مسارات لوحة التحكم (Dashboard) ===
    Route::get('/dashboard/overview', [App\Http\Controllers\Api\DashboardController::class, 'getOverview']);
    
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
    
    // إضافة البيانات من قبل المدير العام (POST)
    Route::post('/lookups/screen-types', [App\Http\Controllers\Api\LookupController::class, 'storeScreenType']);
    Route::post('/lookups/governorates', [App\Http\Controllers\Api\LookupController::class, 'storeGovernorate']);
    Route::post('/lookups/regions', [App\Http\Controllers\Api\LookupController::class, 'storeRegion']);
    Route::post('/lookups/streets', [App\Http\Controllers\Api\LookupController::class, 'storeStreet']);
    Route::post('/lookups/full-location', [App\Http\Controllers\Api\LookupController::class, 'storeFullLocation']);
    
    // تعديل وحذف البيانات من قبل المدير العام (PUT & DELETE)
    Route::put('/lookups/governorates/{id}', [App\Http\Controllers\Api\LookupController::class, 'updateGovernorate']);
    Route::delete('/lookups/governorates/{id}', [App\Http\Controllers\Api\LookupController::class, 'destroyGovernorate']);
    
    Route::put('/lookups/regions/{id}', [App\Http\Controllers\Api\LookupController::class, 'updateRegion']);
    Route::delete('/lookups/regions/{id}', [App\Http\Controllers\Api\LookupController::class, 'destroyRegion']);
    
    Route::put('/lookups/streets/{id}', [App\Http\Controllers\Api\LookupController::class, 'updateStreet']);
    Route::delete('/lookups/streets/{id}', [App\Http\Controllers\Api\LookupController::class, 'destroyStreet']);
    
});