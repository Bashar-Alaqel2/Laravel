<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DurationDiscount;
use Illuminate\Http\Request;

class DurationDiscountController extends Controller
{
    /**
     * Display a listing of the duration discounts.
     */
    public function index(Request $request)
    {
        $discounts = DurationDiscount::orderBy('min_days', 'asc')->get();
        return response()->json([
            'success' => true,
            'data'    => $discounts
        ]);
    }

    /**
     * Store a newly created duration discount in storage.
     */
    public function store(Request $request)
    {
        // التحقق من الصلاحيات للمدير
        if (!$request->user()->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بإضافة الخصومات.'], 403);
        }

        $request->validate([
            'name'                => 'nullable|string|max:255',
            'min_days'            => 'required|integer|min:1|unique:duration_discounts,min_days',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'is_active'           => 'boolean'
        ]);

        $discount = DurationDiscount::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة الخصم بنجاح.',
            'data'    => $discount
        ], 201);
    }

    /**
     * Display the specified duration discount.
     */
    public function show($id)
    {
        $discount = DurationDiscount::findOrFail($id);
        return response()->json([
            'success' => true,
            'data'    => $discount
        ]);
    }

    /**
     * Update the specified duration discount in storage.
     */
    public function update(Request $request, $id)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بتعديل الخصومات.'], 403);
        }

        $discount = DurationDiscount::findOrFail($id);

        $request->validate([
            'name'                => 'nullable|string|max:255',
            'min_days'            => 'required|integer|min:1|unique:duration_discounts,min_days,' . $id . ',duration_discount_id',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'is_active'           => 'boolean'
        ]);

        $discount->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل الخصم بنجاح.',
            'data'    => $discount
        ]);
    }

    /**
     * Remove the specified duration discount from storage.
     */
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'غير مصرح لك بحذف الخصومات.'], 403);
        }

        $discount = DurationDiscount::findOrFail($id);
        $discount->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف الخصم بنجاح.'
        ]);
    }
}
