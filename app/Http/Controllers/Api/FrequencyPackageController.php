<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FrequencyPackage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FrequencyPackageController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => FrequencyPackage::orderBy('display_interval', 'desc')->get()
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:frequency_packages,name',
            'display_interval' => 'required|integer|min:1',
            'price_multiplier' => 'required|numeric|min:0.01',
        ], [
            'name.required' => 'يرجى إدخال اسم الباقة.',
            'name.unique' => 'اسم الباقة مسجل مسبقاً.',
            'display_interval.required' => 'يرجى إدخال تكرار العرض بالدقائق.',
            'price_multiplier.required' => 'يرجى إدخال مضاعف السعر.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $package = FrequencyPackage::create([
            'name' => $request->name,
            'display_interval' => $request->display_interval,
            'price_multiplier' => $request->price_multiplier,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة باقة التكرار بنجاح.',
            'data' => $package
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $package = FrequencyPackage::findOrFail($id);
        return response()->json([
            'success' => true,
            'data' => $package
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $package = FrequencyPackage::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100|unique:frequency_packages,name,' . $id,
            'display_interval' => 'required|integer|min:1',
            'price_multiplier' => 'required|numeric|min:0.01',
        ], [
            'name.required' => 'يرجى إدخال اسم الباقة.',
            'name.unique' => 'اسم الباقة مسجل مسبقاً.',
            'display_interval.required' => 'يرجى إدخال تكرار العرض بالدقائق.',
            'price_multiplier.required' => 'يرجى إدخال مضاعف السعر.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $package->update([
            'name' => $request->name,
            'display_interval' => $request->display_interval,
            'price_multiplier' => $request->price_multiplier,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم تحديث باقة التكرار بنجاح.',
            'data' => $package
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $package = FrequencyPackage::findOrFail($id);
        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف باقة التكرار بنجاح.'
        ], 200);
    }
}
