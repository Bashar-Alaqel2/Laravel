<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إضافة أنواع الشاشات (ضرورية للنظام)
        $screenTypes = [
            ['type_name' => 'شاشة عرض طولية', 'resolution_width' => 1080, 'resolution_height' => 1920, 'orientation' => 'portrait'],
            ['type_name' => 'شاشة عرض عرضية', 'resolution_width' => 1920, 'resolution_height' => 1080, 'orientation' => 'landscape'],
        ];

        foreach ($screenTypes as $type) {
            DB::table('screen_types')->updateOrInsert(['type_name' => $type['type_name']], $type);
        }

        // 2. إضافة الأقسام الأساسية
        $categories = [
            ['category_name' => 'إعلانات تجارية', 'price' => 10, 'max_duration' => 60, 'max_size' => 100],
            ['category_name' => 'إعلانات عامة', 'price' => 5, 'max_duration' => 30, 'max_size' => 50],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(['category_name' => $cat['category_name']], $cat);
        }

        // 3. إضافة المحافظات الأساسية (لكي تظهر في واجهة إضافة شاشة)
        $governorates = [
            ['name' => 'صنعاء'],
            ['name' => 'عدن'],
            ['name' => 'تعز'],
            ['name' => 'حضرموت'],
            ['name' => 'الحديدة'],
            ['name' => 'إب'],
        ];

        foreach ($governorates as $gov) {
            DB::table('governorates')->updateOrInsert(['name' => $gov['name']], $gov);
        }
    }
}
