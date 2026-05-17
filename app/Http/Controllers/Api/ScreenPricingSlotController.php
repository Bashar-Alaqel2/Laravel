<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScreenPricingSlot;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ScreenPricingSlotController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $query = ScreenPricingSlot::with('screen');

        if ($request->has('screen_id')) {
            $query->where('screen_id', $request->screen_id);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        if (!$user->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'screen_id'        => 'required|exists:screens,screen_id',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'price_multiplier' => 'required|numeric|min:0.1|max:10.0',
        ], [
            'end_time.after' => 'يجب أن يكون وقت النهاية بعد وقت البدء.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        // التحقق من عدم وجود تداخل لأوقات الذروة لنفس الشاشة
        $overlap = ScreenPricingSlot::where('screen_id', $request->screen_id)
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                  ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        if ($overlap) {
            return response()->json(['success' => false, 'message' => 'عذراً، يوجد تداخل في الأوقات المحددة مع ذروة أخرى مسجلة لهذه الشاشة.'], 400);
        }

        $slot = ScreenPricingSlot::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة وقت الذروة بنجاح',
            'data' => $slot->load('screen')
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $slot = ScreenPricingSlot::with('screen')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $slot
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $slot = ScreenPricingSlot::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'screen_id'        => 'required|exists:screens,screen_id',
            'start_time'       => 'required|date_format:H:i',
            'end_time'         => 'required|date_format:H:i|after:start_time',
            'price_multiplier' => 'required|numeric|min:0.1|max:10.0',
        ], [
            'end_time.after' => 'يجب أن يكون وقت النهاية بعد وقت البدء.',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 400);
        }

        // التحقق من عدم وجود تداخل لأوقات الذروة لنفس الشاشة (مع استثناء الحالي)
        $overlap = ScreenPricingSlot::where('screen_id', $request->screen_id)
            ->where('slot_id', '!=', $id)
            ->where(function ($q) use ($request) {
                $q->where('start_time', '<', $request->end_time)
                  ->where('end_time', '>', $request->start_time);
            })
            ->exists();

        if ($overlap) {
            return response()->json(['success' => false, 'message' => 'عذراً، يوجد تداخل في الأوقات المحددة مع ذروة أخرى مسجلة لهذه الشاشة.'], 400);
        }

        $slot->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'تم تعديل وقت الذروة بنجاح',
            'data' => $slot->load('screen')
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->can('manage_all')) {
            return response()->json(['success' => false, 'message' => 'هذه الصلاحية للمدير العام فقط.'], 403);
        }

        $slot = ScreenPricingSlot::findOrFail($id);
        $slot->delete();

        return response()->json([
            'success' => true,
            'message' => 'تم حذف وقت الذروة بنجاح'
        ], 200);
    }
}
