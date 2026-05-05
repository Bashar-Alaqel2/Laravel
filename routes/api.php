<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

use App\Http\Controllers\Api\ScreenController;

// مسارات مفتوحة (لا تحتاج تسجيل دخول)
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// مسارات محمية (يجب إرسال التوكن للوصول إليها)
Route::middleware('auth:sanctum')->group(function () {
    
    // تسجيل الخروج
    Route::post('/logout', [AuthController::class, 'logout']);
    
    // === مسارات الشاشات (للوحة التحكم ولتطبيق Flutter) ===
    Route::apiResource('screens', ScreenController::class); // تنشئ مسارات: index, store, show, update, destroy
    Route::post('screens/{id}/ping', [ScreenController::class, 'ping']); // مسار خاص لنبض الشاشة (Flutter)
    
    // === مسارات الإعلانات ===
    Route::get('/ads', [App\Http\Controllers\Api\AdController::class, 'index']);
    Route::post('/ads', [App\Http\Controllers\Api\AdController::class, 'store']);
    Route::put('/ads/{id}/status', [App\Http\Controllers\Api\AdController::class, 'updateStatus']);
    Route::delete('/ads/{id}', [App\Http\Controllers\Api\AdController::class, 'destroy']);
    
    // === مسارات القوائم المنسدلة (Lookups) ===
    Route::get('/lookups/screen-types', [App\Http\Controllers\Api\LookupController::class, 'getScreenTypes']);
    Route::get('/lookups/streets', [App\Http\Controllers\Api\LookupController::class, 'getStreets']);
    Route::get('/lookups/categories', [App\Http\Controllers\Api\LookupController::class, 'getCategories']);
    
});