<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScreenType;
use App\Models\Governorate;
use App\Models\Region;
use App\Models\Street;
use App\Models\Category;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    // =======================================
    // 1. القراءة (GET) للواجهات
    // =======================================
    public function getScreenTypes()
    {
        return response()->json(ScreenType::all(), 200);
    }

    public function getGovernorates()
    {
        return response()->json(Governorate::all(), 200);
    }

    public function getRegions($gov_id)
    {
        return response()->json(Region::where('gov_id', $gov_id)->get(), 200);
    }

    public function getStreets($region_id)
    {
        // نجلب الشوارع مع المنطقة والمحافظة التابعة لها ليكون الاسم واضحاً
        return response()->json(Street::with('region.governorate')->where('region_id', $region_id)->get(), 200);
    }

    public function getAllStreets()
    {
        return response()->json(Street::with('region.governorate')->get(), 200);
    }

    public function getCategories()
    {
        return response()->json(Category::all(), 200);
    }

    // =======================================
    // 2. الإضافة (POST) للمدير (System Configuration)
    // =======================================

    // إضافة نوع شاشة
    public function storeScreenType(Request $request)
    {
        // التحقق من أن المستخدم هو المدير العام
        if (!$request->user()->can('manage_all')) {
            return response()->json(['error' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $request->validate([
            'type_name'         => 'required|string|max:100|unique:screen_types,type_name',
            'resolution_width'  => 'required|integer',
            'resolution_height' => 'required|integer',
            'orientation'       => 'required|in:landscape,portrait'
        ]);

        $type = ScreenType::create($request->all());
        return response()->json(['message' => 'تم إضافة نوع الشاشة بنجاح', 'data' => $type], 201);
    }

    // إضافة محافظة
    public function storeGovernorate(Request $request)
    {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);

        $request->validate(['name' => 'required|string|max:100|unique:governorates,name']);
        
        $gov = Governorate::create($request->only('name'));
        return response()->json(['message' => 'تم إضافة المحافظة بنجاح', 'data' => $gov], 201);
    }

    // إضافة منطقة
    public function storeRegion(Request $request)
    {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);

        $request->validate([
            'name'   => 'required|string|max:100',
            'gov_id' => 'required|exists:governorates,gov_id'
        ]);
        
        $region = Region::create($request->only(['name', 'gov_id']));
        return response()->json(['message' => 'تم إضافة المنطقة بنجاح', 'data' => $region], 201);
    }

    // إضافة شارع
    public function storeStreet(Request $request)
    {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);

        $request->validate([
            'name'      => 'required|string|max:100',
            'region_id' => 'required|exists:regions,region_id'
        ]);
        
        $street = Street::create($request->only(['name', 'region_id']));
        return response()->json(['message' => 'تم إضافة الشارع بنجاح', 'data' => $street], 201);
    }

    // =======================================
    // 3. التعديل والحذف (PUT / DELETE) للمدير
    // =======================================

    // المحافظات
    public function updateGovernorate(Request $request, $id) {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);
        $gov = Governorate::findOrFail($id);
        $request->validate(['name' => 'required|string|max:100|unique:governorates,name,'.$id.',gov_id']);
        $gov->update($request->only('name'));
        return response()->json(['message' => 'تم تعديل المحافظة بنجاح', 'data' => $gov], 200);
    }
    public function destroyGovernorate(Request $request, $id) {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);
        Governorate::findOrFail($id)->delete(); // الحذف سيمسح كل المناطق والشوارع التابعة بفضل Cascade
        return response()->json(['message' => 'تم حذف المحافظة ومحتوياتها بنجاح'], 200);
    }

    // المناطق
    public function updateRegion(Request $request, $id) {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);
        $region = Region::findOrFail($id);
        $request->validate([
            'name'   => 'required|string|max:100',
            'gov_id' => 'required|exists:governorates,gov_id'
        ]);
        $region->update($request->only(['name', 'gov_id']));
        return response()->json(['message' => 'تم تعديل المنطقة بنجاح', 'data' => $region], 200);
    }
    public function destroyRegion(Request $request, $id) {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);
        Region::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف المنطقة بنجاح'], 200);
    }

    // الشوارع
    public function updateStreet(Request $request, $id) {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);
        $street = Street::findOrFail($id);
        $request->validate([
            'name'      => 'required|string|max:100',
            'region_id' => 'required|exists:regions,region_id'
        ]);
        $street->update($request->only(['name', 'region_id']));
        return response()->json(['message' => 'تم تعديل الشارع بنجاح', 'data' => $street], 200);
    }
    public function destroyStreet(Request $request, $id) {
        if (!$request->user()->can('manage_all')) return response()->json(['error' => 'ممنوع'], 403);
        Street::findOrFail($id)->delete();
        return response()->json(['message' => 'تم حذف الشارع بنجاح'], 200);
    }
}
