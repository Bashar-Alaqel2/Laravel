<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScreenType;
use App\Models\Governorate;
use App\Models\Region;
use App\Models\Street;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class LookupController extends Controller
{
    // =======================================
    // 1. القراءة (GET) للواجهات
    // =======================================
    public function getScreenTypes()
    {
        return response()->json(Cache::remember('lookup_screen_types', 86400, function () {
            return ScreenType::all()->toArray();
        }), 200);
    }

    public function getGovernorates()
    {
        return response()->json(Cache::remember('lookup_governorates', 86400, function () {
            return Governorate::all()->toArray();
        }), 200);
    }

    public function getRegions($gov_id)
    {
        return response()->json(Cache::remember("lookup_regions_gov_{$gov_id}", 86400, function () use ($gov_id) {
            return Region::where('gov_id', $gov_id)->get();
        }), 200);
    }

    public function getAllRegions()
    {
        return response()->json(Cache::remember('lookup_all_regions', 86400, function () {
            return Region::with('governorate')->get()->toArray();
        }), 200);
    }

    public function getStreets($region_id)
    {
        // نجلب الشوارع مع المنطقة والمحافظة التابعة لها ليكون الاسم واضحاً
        return response()->json(Cache::remember("lookup_streets_reg_{$region_id}", 86400, function () use ($region_id) {
            return Street::with('region.governorate')->where('region_id', $region_id)->get();
        }), 200);
    }

    public function getAllStreets()
    {
        return response()->json(Cache::remember('lookup_all_streets', 86400, function () {
            return Street::with('region.governorate')->get()->toArray();
        }), 200);
    }

    public function getCategories()
    {
        return response()->json(Cache::remember('lookup_categories', 86400, function () {
            return Category::all()->toArray();
        }), 200);
    }

    public function getUsersByRole($roleName)
    {
        $cacheKey = 'lookup_users_role_' . strtolower($roleName);
        return response()->json(Cache::remember($cacheKey, 3600, function () use ($roleName) {
            return \App\Models\User::with('role')->whereHas('role', function($query) use ($roleName) {
                $query->where('role_name', $roleName);
            })->get(['user_id', 'role_id', 'full_name', 'email'])->toArray();
        }), 200);
    }

    public function getRoles()
    {
        return response()->json(\Illuminate\Support\Facades\Cache::remember('lookup_roles', 86400, function () {
            return \App\Models\Role::all()->toArray();
        }), 200);
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
        Cache::forget('lookup_screen_types');
        return response()->json(['message' => 'تم إضافة نوع الشاشة بنجاح', 'data' => $type], 201);
    }

    // إضافة محافظة
    public function storeGovernorate(Request $request)
    {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);

        $request->validate(['name' => 'required|string|max:100|unique:governorates,name']);
        
        $gov = Governorate::create($request->only('name'));
        Cache::forget('lookup_governorates');
        return response()->json(['message' => 'تم إضافة المحافظة بنجاح', 'data' => $gov], 201);
    }

    // إضافة منطقة
    public function storeRegion(Request $request)
    {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);

        $request->validate([
            'name'   => 'required|string|max:100',
            'gov_id' => 'required|exists:governorates,gov_id'
        ]);
        
        $region = Region::create($request->only(['name', 'gov_id']));
        Cache::forget('lookup_all_regions');
        Cache::forget('lookup_regions_gov_' . $region->gov_id);
        return response()->json(['message' => 'تم إضافة المنطقة بنجاح', 'data' => $region], 201);
    }

    // إضافة شارع
    public function storeStreet(Request $request)
    {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);

        $request->validate([
            'name'      => 'required|string|max:100',
            'region_id' => 'required|exists:regions,region_id'
        ]);
        
        $street = Street::create($request->only(['name', 'region_id']));
        Cache::forget('lookup_all_streets');
        Cache::forget('lookup_streets_reg_' . $street->region_id);
        return response()->json(['message' => 'تم إضافة الشارع بنجاح', 'data' => $street], 201);
    }

    // إضافة موقع كامل (محافظة، مدينة، شارع) دفعة واحدة
    public function storeFullLocation(Request $request)
    {
        // يمكن تفعيل التحقق من الصلاحيات لاحقاً
        // if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);

        $request->validate([
            'governorate' => 'required|string|max:100',
            'city'        => 'required|string|max:100',
            'street'      => 'required|string|max:100',
        ]);

        // البحث أو الإنشاء للمحافظة
        $gov = Governorate::firstOrCreate(['name' => $request->governorate]);
        
        // البحث أو الإنشاء للمدينة/المنطقة
        $region = Region::firstOrCreate([
            'name' => $request->city,
            'gov_id' => $gov->gov_id
        ]);
        
        // البحث أو الإنشاء للشارع
        $street = Street::firstOrCreate([
            'name' => $request->street,
            'region_id' => $region->region_id
        ]);

        Cache::forget('lookup_governorates');
        Cache::forget('lookup_all_regions');
        Cache::forget('lookup_all_streets');
        Cache::forget('lookup_regions_gov_' . $gov->gov_id);
        Cache::forget('lookup_streets_reg_' . $region->region_id);

        return response()->json([
            'message' => 'تم إضافة الموقع بالكامل بنجاح',
            'data' => [
                'governorate' => $gov,
                'region' => $region,
                'street' => $street,
            ]
        ], 201);
    }

    // =======================================
    // 3. التعديل والحذف (PUT / DELETE) للمدير
    // =======================================

    // المحافظات
    public function updateGovernorate(Request $request, $id) {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);
        $gov = Governorate::findOrFail($id);
        $request->validate(['name' => 'required|string|max:100|unique:governorates,name,'.$id.',gov_id']);
        $gov->update($request->only('name'));
        Cache::forget('lookup_governorates');
        return response()->json(['message' => 'تم تعديل المحافظة بنجاح', 'data' => $gov], 200);
    }
    public function destroyGovernorate(Request $request, $id) {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);
        Governorate::findOrFail($id)->delete(); // الحذف سيمسح كل المناطق والشوارع التابعة بفضل Cascade
        Cache::forget('lookup_governorates');
        Cache::forget('lookup_all_regions');
        Cache::forget('lookup_all_streets');
        return response()->json(['message' => 'تم حذف المحافظة ومحتوياتها بنجاح'], 200);
    }

    // المناطق
    public function updateRegion(Request $request, $id) {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);
        $region = Region::findOrFail($id);
        $request->validate([
            'name'   => 'required|string|max:100',
            'gov_id' => 'required|exists:governorates,gov_id'
        ]);
        $region->update($request->only(['name', 'gov_id']));
        Cache::forget('lookup_all_regions');
        Cache::forget('lookup_regions_gov_' . $region->gov_id);
        return response()->json(['message' => 'تم تعديل المنطقة بنجاح', 'data' => $region], 200);
    }
    public function destroyRegion(Request $request, $id) {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);
        $region = Region::findOrFail($id);
        $gov_id = $region->gov_id;
        $region->delete();
        Cache::forget('lookup_all_regions');
        Cache::forget('lookup_regions_gov_' . $gov_id);
        Cache::forget('lookup_all_streets');
        return response()->json(['message' => 'تم حذف المنطقة بنجاح'], 200);
    }

    // الشوارع
    public function updateStreet(Request $request, $id) {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);
        $street = Street::findOrFail($id);
        $request->validate([
            'name'      => 'required|string|max:100',
            'region_id' => 'required|exists:regions,region_id'
        ]);
        $street->update($request->only(['name', 'region_id']));
        Cache::forget('lookup_all_streets');
        Cache::forget('lookup_streets_reg_' . $street->region_id);
        return response()->json(['message' => 'تم تعديل الشارع بنجاح', 'data' => $street], 200);
    }
    public function destroyStreet(Request $request, $id) {
        if (!$request->user()->can('manage_all') && !$request->user()->can('manage_regions')) return response()->json(['error' => 'ممنوع'], 403);
        $street = Street::findOrFail($id);
        $region_id = $street->region_id;
        $street->delete();
        Cache::forget('lookup_all_streets');
        Cache::forget('lookup_streets_reg_' . $region_id);
        return response()->json(['message' => 'تم حذف الشارع بنجاح'], 200);
    }

    // إدارة الأدوار (Roles Management)
    public function storeRole(Request $request)
    {
        return response()->json(['message' => 'إضافة أدوار جديدة غير مسموحة في النظام.'], 403);
    }

    public function storeCategory(Request $request)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['error' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $request->validate([
            'category_name' => 'required|string|max:100|unique:categories,category_name',
            'price'         => 'required|numeric',
            'max_duration'  => 'required|integer',
            'max_size'      => 'required|integer',
        ]);

        $category = Category::create([
            'category_name' => $request->category_name,
            'price'         => $request->price,
            'max_duration'  => $request->max_duration,
            'max_size'      => $request->max_size,
        ]);

        Cache::forget('lookup_categories');

        return response()->json(['message' => 'تم إضافة التصنيف بنجاح', 'data' => $category], 201);
    }

    public function updateRole(Request $request, $id)
    {
        $role = \App\Models\Role::findOrFail($id);
        $request->validate(['role_name' => 'required|string|max:50|unique:roles,role_name,'.$id.',role_id']);
        $role->update(['role_name' => $request->role_name]);
        Cache::forget('lookup_roles');
        // Clear all role-specific users caches
        Cache::forget('lookup_users_role_' . strtolower($role->role_name));
        return response()->json(['message' => 'تم تعديل الدور بنجاح', 'data' => $role], 200);
    }

    public function destroyRole($id)
    {
        return response()->json(['message' => 'حذف الأدوار غير مسموح في النظام.'], 403);
    }

    public function updateCategory(Request $request, $id)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['error' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $request->validate([
            'category_name' => 'required|string|max:100|unique:categories,category_name,'.$id.',category_id',
            'price'         => 'required|numeric',
            'max_duration'  => 'required|integer',
            'max_size'      => 'required|integer',
        ]);

        $category = \App\Models\Category::findOrFail($id);
        $category->update([
            'category_name' => $request->category_name,
            'price'         => $request->price,
            'max_duration'  => $request->max_duration,
            'max_size'      => $request->max_size,
        ]);

        Cache::forget('lookup_categories');

        return response()->json(['message' => 'تم تحديث التصنيف بنجاح', 'data' => $category], 200);
    }

    public function destroyCategory(Request $request, $id)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['error' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $category = \App\Models\Category::findOrFail($id);
        $category->delete();

        Cache::forget('lookup_categories');

        return response()->json(['message' => 'تم حذف التصنيف بنجاح'], 200);
    }
}



