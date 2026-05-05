<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScreenType;
use App\Models\Street;
use App\Models\Category;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * جلب أنواع الشاشات لاستخدامها في القوائم المنسدلة
     */
    public function getScreenTypes()
    {
        // نجلب جميع الأنواع المتاحة
        $types = ScreenType::all();
        return response()->json($types, 200);
    }

    /**
     * جلب الشوارع لاستخدامها في تحديد موقع الشاشة
     */
    public function getStreets()
    {
        // نجلب الشوارع مع المنطقة والمحافظة التابعة لها ليكون الاسم واضحاً للمستخدم
        $streets = Street::with('region.governorate')->get();
        return response()->json($streets, 200);
    }

    /**
     * جلب الأقسام الإعلانية (للاستخدام لاحقاً في الإعلانات)
     */
    public function getCategories()
    {
        $categories = Category::all();
        return response()->json($categories, 200);
    }
}
