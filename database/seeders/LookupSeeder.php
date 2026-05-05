<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LookupSeeder extends Seeder
{
    public function run(): void
    {
        // 1. إضافة أنواع الشاشات
        $screenTypes = [
            ['type_name' => 'شاشة عرض طولية', 'resolution_width' => 1080, 'resolution_height' => 1920, 'orientation' => 'portrait'],
            ['type_name' => 'شاشة عرض عرضية', 'resolution_width' => 1920, 'resolution_height' => 1080, 'orientation' => 'landscape'],
            ['type_name' => 'شاشة إعلانات ضخمة (Billboard)', 'resolution_width' => 3840, 'resolution_height' => 2160, 'orientation' => 'landscape'],
        ];

        foreach ($screenTypes as $type) {
            DB::table('screen_types')->updateOrInsert(['type_name' => $type['type_name']], $type);
        }

        // 2. إضافة محافظة ومنطقة وشارع
        $govId = DB::table('governorates')->insertGetId(['governorate_name' => 'صنعاء']);
        $regId = DB::table('regions')->insertGetId(['governorate_id' => $govId, 'region_name' => 'حدة']);
        
        DB::table('streets')->updateOrInsert(
            ['street_name' => 'شارع حدة الرئيسي'],
            ['region_id' => $regId]
        );
        
        DB::table('streets')->updateOrInsert(
            ['street_name' => 'شارع الستين'],
            ['region_id' => $regId]
        );

        // 3. إضافة أقسام إعلانية
        $categories = [
            ['category_name' => 'إعلانات تجارية'],
            ['category_name' => 'إعلانات تعليمية'],
            ['category_name' => 'توعية وإرشادات'],
        ];

        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(['category_name' => $cat['category_name']], $cat);
        }
    }
}
