<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['role_name' => 'SuperAdmin',  'description' => 'المدير العام للنظام بصلاحيات مطلقة'],
            ['role_name' => 'Advertiser',  'description' => 'معلن يقوم برفع الإعلانات ودفع تكلفتها'],
            ['role_name' => 'ScreenOwner', 'description' => 'مالك شاشات يقوم بتأجيرها للنظام'],
            ['role_name' => 'Maintenance', 'description' => 'فريق الصيانة والدعم الفني'],
            ['role_name' => 'Accountant',  'description' => 'المحاسب المالي للنظام'],
            ['role_name' => 'Secretary',   'description' => 'سكرتير ومشرف على مراجعة المحتوى']
        ];

        // إدخال الأدوار فقط إذا لم تكن موجودة مسبقاً لتجنب التكرار
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_name' => $role['role_name']],
                ['description' => $role['description'], 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }
}
