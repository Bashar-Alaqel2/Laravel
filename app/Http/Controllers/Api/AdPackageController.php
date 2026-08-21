<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdPackage;
use Illuminate\Http\Request;

class AdPackageController extends Controller
{
    /**
     * Display a listing of all packages (Admin View).
     */
    public function index()
    {
        $packages = AdPackage::orderBy('interval_minutes', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $packages
        ]);
    }

    /**
     * Display a listing of active packages (Advertiser View).
     */
    public function active()
    {
        $packages = AdPackage::where('is_active', true)->orderBy('interval_minutes', 'asc')->get();
        return response()->json([
            'success' => true,
            'data' => $packages
        ]);
    }

    /**
     * Store a newly created package.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'interval_minutes' => 'required|integer|min:1',
            'price_multiplier' => 'required|numeric|min:0.01',
            'is_active' => 'boolean',
        ]);

        $package = AdPackage::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Package created successfully',
            'data' => $package
        ]);
    }

    /**
     * Update the specified package.
     */
    public function update(Request $request, $id)
    {
        $package = AdPackage::findOrFail($id);

        $request->validate([
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'interval_minutes' => 'required|integer|min:1',
            'price_multiplier' => 'required|numeric|min:0.01',
            'is_active' => 'boolean',
        ]);

        $package->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Package updated successfully',
            'data' => $package
        ]);
    }

    /**
     * Remove the specified package.
     */
    public function destroy($id)
    {
        $package = AdPackage::findOrFail($id);
        $package->delete();

        return response()->json([
            'success' => true,
            'message' => 'Package deleted successfully'
        ]);
    }
}
