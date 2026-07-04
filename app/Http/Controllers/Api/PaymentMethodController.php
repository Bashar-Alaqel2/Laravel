<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Validator;

class PaymentMethodController extends Controller
{
    // عرض كل الوسائل (للأدمن أو المعلن)
    public function index(Request $request)
    {
        $query = PaymentMethod::query();
        
        // إذا لم يكن أدمن، يرى فقط النشط
        if (!$request->user()->can('manage_all')) {
            $query->where('is_active', 'true');
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    // إضافة وسيلة دفع جديدة (للأدمن فقط)
    public function store(Request $request)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['error' => 'غير مصرح لك'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name'            => 'required|string|max:255',
            'account_details' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        $method = PaymentMethod::create([
            'name'                   => $request->name,
            'account_details'        => $request->account_details,
            'stripe_publishable_key' => !empty(trim($request->stripe_publishable_key)) ? trim($request->stripe_publishable_key) : null,
            'stripe_secret_key'      => !empty(trim($request->stripe_secret_key)) ? trim($request->stripe_secret_key) : null,
            'is_active'              => \Illuminate\Support\Facades\DB::raw('true'),
        ]);

        return response()->json(['success' => true, 'data' => $method], 201);
    }

    // تحديث وسيلة دفع
    public function update(Request $request, $id)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['error' => 'غير مصرح لك'], 403);
        }

        $method = PaymentMethod::findOrFail($id);
        
        $data = $request->only(['name', 'account_details']);
        if ($request->has('is_active')) {
            $data['is_active'] = $request->boolean('is_active') ? \Illuminate\Support\Facades\DB::raw('true') : \Illuminate\Support\Facades\DB::raw('false');
        }
        
        if ($request->has('stripe_publishable_key')) {
            $data['stripe_publishable_key'] = !empty(trim($request->stripe_publishable_key)) ? trim($request->stripe_publishable_key) : null;
        }
        if ($request->has('stripe_secret_key')) {
            $data['stripe_secret_key'] = !empty(trim($request->stripe_secret_key)) ? trim($request->stripe_secret_key) : null;
        }

        $method->update($data);

        return response()->json(['success' => true, 'data' => $method]);
    }

    // حذف وسيلة دفع
    public function destroy(Request $request, $id)
    {
        if (!$request->user()->can('manage_all')) {
            return response()->json(['error' => 'غير مصرح لك'], 403);
        }

        $method = PaymentMethod::findOrFail($id);
        $method->delete();

        return response()->json(['success' => true, 'message' => 'تم الحذف بنجاح']);
    }
}
