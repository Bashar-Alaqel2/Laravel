<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// رابط إنشاء حساب جديد
Route::post('/register', [AuthController::class, 'register']);

// رابط تسجيل الدخول
Route::post('/login', [AuthController::class, 'login']);