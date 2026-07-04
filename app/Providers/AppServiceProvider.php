<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // تسجيل نظام الصلاحيات المركزي
        \Illuminate\Support\Facades\Gate::before(function ($user, $ability) {
            // التحقق من وجود دور للمستخدم
            if (!$user->role) {
                return false;
            }

            // إحضار اسم الدور من قاعدة البيانات (مثل: SuperAdmin, Advertiser)
            $roleName = $user->role->role_name;

            // إذا كان المستخدم هو المدير العام (Super Admin) أو مدير (Admin)، نعطيه كل الصلاحيات مطلقاً
            if ($roleName === 'SuperAdmin' || $roleName === 'Admin') {
                return true;
            }

            // جلب قائمة الصلاحيات الخاصة بهذا الدور من ملف config/permissions.php
            $allowedPermissions = config("permissions.roles.{$roleName}", []);

            // إذا كانت الصلاحية المطلوبة موجودة في مصفوفة الدور، نسمح بالعملية
            if (in_array($ability, $allowedPermissions)) {
                return true;
            }

            // إذا لم تكن موجودة، يعود Laravel للوضع الافتراضي (وهو الرفض)
        });
    }
}
