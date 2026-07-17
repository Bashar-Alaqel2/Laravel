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
            if (!$user->role_id) {
                return false;
            }

            // خريطة داخلية لربط رقم الدور باسم الصلاحيات في ملف config
            $roleMap = [
                \App\Models\Role::SUPER_ADMIN => 'SuperAdmin',
                \App\Models\Role::ADVERTISER => 'Advertiser',
                \App\Models\Role::SCREEN_OWNER => 'ScreenOwner',
                \App\Models\Role::MAINTENANCE => 'Maintenance',
                \App\Models\Role::SECRETARY => 'Secretary',
                \App\Models\Role::ADMIN => 'Admin',
            ];

            // إحضار اسم الدور الداخلي بناءً على الرقم
            $internalRoleName = $roleMap[$user->role_id] ?? null;

            if (!$internalRoleName) {
                return false;
            }

            // إذا كان المستخدم هو المدير العام (Super Admin) أو مدير (Admin)، نعطيه كل الصلاحيات مطلقاً
            if ($internalRoleName === 'SuperAdmin' || $internalRoleName === 'Admin') {
                return true;
            }

            // جلب قائمة الصلاحيات الخاصة بهذا الدور من ملف config/permissions.php
            $allowedPermissions = config("permissions.roles.{$internalRoleName}", []);

            // إذا كانت الصلاحية المطلوبة موجودة في مصفوفة الدور، نسمح بالعملية
            if (in_array($ability, $allowedPermissions)) {
                return true;
            }

            // إذا لم تكن موجودة، يعود Laravel للوضع الافتراضي (وهو الرفض)
        });
    }
}
