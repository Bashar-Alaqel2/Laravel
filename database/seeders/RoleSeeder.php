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
            ['role_name' => 'SuperAdmin'],
            ['role_name' => 'Advertiser'],
            ['role_name' => 'ScreenOwner'],
            ['role_name' => 'Maintenance'],
            ['role_name' => 'Accountant'],
            ['role_name' => 'Secretary']
        ];

        // إدخال الأدوار فقط إذا لم تكن موجودة مسبقاً لتجنب التكرار
        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['role_name' => $role['role_name']],
                []
            );
        }
    }
}
