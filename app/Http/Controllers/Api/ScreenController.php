<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Screen;

class ScreenController extends Controller
{
    // ==========================================
    // 1. جلب جميع الشاشات (للوحة التحكم - React - زميلك علي)
    // ==========================================
    public function index()
    {
        $user = request()->user();
        $query = Screen::with(['owner', 'type', 'street.region.governorate']);

        if ($user) {
            if ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner')) {
                $query->where('owner_id', $user->user_id);
            }
        }

        $screens = $query->get();
        return response()->json($screens, 200);
    }

    // ==========================================
    // 2. إضافة شاشة جديدة (لوحة التحكم)
    // ==========================================
    public function store(Request $request)
    {
        // التحقق من صحة البيانات
        $request->validate([
            'screen_name' => 'required|string|max:100',
            'mac_address' => 'required|string', // لا نتحقق من unique هنا لأننا سنقوم بتعديل السجل المؤقت
            'type_id'     => 'nullable|exists:screen_types,type_id',
            'street_id'   => 'nullable|exists:streets,street_id',
            'owner_id'    => 'nullable|exists:users,user_id',
            'linked_by'   => 'nullable|exists:users,user_id',
            'photo'       => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // الصورة اختيارية
            'latitude'    => 'nullable|numeric',
            'longitude'   => 'nullable|numeric',
        ]);

        // التحقق من المعرف المدخل وتنسيقه (دعم مع أو بدون بادئة SB-)
        $macAddress = strtoupper(trim($request->mac_address));
        $possibleMacs = [$macAddress];
        
        if (str_starts_with($macAddress, 'SB-')) {
            $possibleMacs[] = str_replace('SB-', '', $macAddress);
        } else {
            $possibleMacs[] = 'SB-' . $macAddress;
        }

        // التحقق من أن المعرف قد تم توليده مسبقاً من السيرفر وهو قيد التفعيل
        // البحث عن الشاشة بما في ذلك المحذوفة مؤقتاً
        $screen = Screen::withTrashed()
                        ->whereIn('mac_address', $possibleMacs)
                        ->first();

        if ($screen) {
            // إذا كانت الشاشة غير محذوفة وليست بانتظار التفعيل
            if (!$screen->trashed() && $screen->status !== 'pending_activation') {
                return response()->json([
                    'success' => false,
                    'message' => 'هذا المعرف مستخدم ومضاف لشاشة أخرى مسبقاً!'
                ], 422);
            }

            // إذا كانت الشاشة محذوفة، نقوم باسترجاعها (Restore) وإعادتها لوضع بانتظار التفعيل
            if ($screen->trashed()) {
                $screen->restore();
                $screen->status = 'pending_activation';
                $screen->save();
            }
        } else {
            // إذا لم تكن موجودة نهائياً، نقوم بإنشائها مؤقتاً لنسمح بتفعيلها
            $screen = Screen::create([
                'screen_name' => 'شاشة غير مفعلة',
                'mac_address' => $possibleMacs[0], // نستخدم المعرف الذي أدخله المستخدم
                'pairing_code'=> $possibleMacs[0],
                'status'      => 'pending_activation',
            ]);
        }

        // معالجة رفع الصورة وتحويلها إلى Base64 لتخزينها في قاعدة البيانات مباشرة
        $imagePath = null;
        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $base64 = base64_encode(file_get_contents($file->getRealPath()));
            $mime = $file->getClientMimeType();
            $imagePath = "data:{$mime};base64,{$base64}";
        }

        // تحديث السجل المؤقت وتفعيله
        $screen->update([
            'owner_id'    => $request->owner_id ?? $request->user()->user_id, 
            'screen_name' => $request->screen_name,
            'type_id'     => $request->type_id,
            'street_id'   => $request->street_id,
            'linked_by'   => $request->linked_by,
            'image_path'  => $imagePath,
            'base_price'  => $request->base_price ?? 10.00,
            'screen_size_inch' => $request->screen_size_inch ?? 55,
            'status'      => 'active', // تفعيل الشاشة لتصبح نشطة
            'linked_at'   => now(),
            'latitude'    => $request->latitude ?? $screen->latitude,
            'longitude'   => $request->longitude ?? $screen->longitude,
        ]);

        // إرسال إشعار للمديرين
        $admins = \App\Models\User::whereHas('role', function($q) {
            $q->whereIn('role_name', ['Admin', 'Secretary', 'SuperAdmin']);
        })->get();
        foreach ($admins as $admin) {
            \App\Models\Notification::create([
                'user_id' => $admin->user_id,
                'title' => json_encode(['key' => 'notif_title_new_screen']),
                'message' => json_encode(['key' => 'notif_msg_new_screen', 'args' => ['name' => $screen->screen_name]]),
                'is_read' => 'false',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'تم تفعيل وإضافة الشاشة بنجاح وربطها بالمعرف المولد من السيرفر',
            'data'    => $screen
        ], 201);
    }

    // ==========================================
    // 3. عرض شاشة محددة
    // ==========================================
    public function show($id)
    {
        $user = request()->user();
        $screen = Screen::with(['owner', 'type', 'street.region.governorate'])->find($id);
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير موجودة'], 404);
        }

        if ($user) {
            if ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner')) {
                if ($screen->owner_id !== $user->user_id) {
                    return response()->json(['message' => 'غير مصرح لك بالوصول لهذه الشاشة'], 403);
                }
            }
        }

        return response()->json($screen, 200);
    }

    // ==========================================
    // 4. تعديل بيانات الشاشة
    // ==========================================
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $screen = Screen::find($id);
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير موجودة'], 404);
        }

        if ($user) {
            if ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner')) {
                if ($screen->owner_id !== $user->user_id) {
                    return response()->json(['message' => 'غير مصرح لك بتعديل هذه الشاشة'], 403);
                }
            }
        }

        $request->validate([
            'screen_name'      => 'nullable|string|max:100',
            'type_id'          => 'nullable|exists:screen_types,type_id',
            'street_id'        => 'nullable|exists:streets,street_id',
            'status'           => 'nullable|in:Online,Offline,Maintenance',
            'base_price'       => 'nullable|numeric|min:0',
            'screen_size_inch' => 'nullable|integer|min:10|max:999',
            'latitude'         => 'nullable|numeric',
            'longitude'        => 'nullable|numeric',
        ]);

        // نقوم بتحديث البيانات التي تم إرسالها فقط
        $screen->update($request->only(['screen_name', 'type_id', 'street_id', 'status', 'base_price', 'screen_size_inch', 'latitude', 'longitude']));

        return response()->json([
            'message' => 'تم تعديل الشاشة بنجاح',
            'screen'  => $screen
        ], 200);
    }

    // ==========================================
    // 5. حذف الشاشة
    // ==========================================
    public function destroy($id)
    {
        $user = request()->user();
        $screen = Screen::find($id);
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير موجودة'], 404);
        }

        if ($user) {
            if ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner')) {
                if ($screen->owner_id !== $user->user_id) {
                    return response()->json(['message' => 'غير مصرح لك بحذف هذه الشاشة'], 403);
                }
            }
        }

        $screen->delete(); 

        return response()->json(['message' => 'تم حذف الشاشة بنجاح'], 200);
    }

    // -----------------------------------------------------------------
    // دوال مخصصة لتطبيق الشاشة (Flutter - زميلك نجم الدين)
    // -----------------------------------------------------------------

    // ==========================================
    // 6. نبض الشاشة (Ping) لتحديث حالتها إلى "متصلة"
    // (يتم إرسال الـ mac_address للتعرف على الشاشة)
    // ==========================================
    public function ping(Request $request)
    {
        $request->validate([
            'mac_address' => 'required|string',
        ]);

        $screen = Screen::where('mac_address', $request->mac_address)->first();
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير مربوطة'], 404);
        }

        // نجعل الشاشة Online ونسجل وقت الاتصال
        $screen->update([
            'status' => 'Online',
            'linked_at' => now(), // وقت آخر نبض/اتصال
        ]);

        return response()->json([
            'success' => true,
            'status' => 'Online',
            'last_seen' => $screen->linked_at
        ], 200);
    }

    // ==========================================
    // 5.5 تسجيل بث الإعلانات (Playback Logs)
    // ==========================================
    public function recordPlaybackLog(Request $request)
    {
        $request->validate([
            'mac_address' => 'required|string',
            'ad_id'       => 'required|integer',
        ]);

        $screen = Screen::where('mac_address', $request->mac_address)->first();
        
        if (!$screen) {
            return response()->json(['message' => 'الشاشة غير مربوطة'], 404);
        }

        // تسجيل مشاهدة جديدة
        \App\Models\PlaybackLog::create([
            'ad_id'     => $request->ad_id,
            'screen_id' => $screen->screen_id,
            'played_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Playback log recorded successfully'
        ], 200);
    }

    // ==========================================
    // 6. ربط الشاشة الفيزيائية بالنظام (من تطبيق التلفاز)
    // ==========================================
    public function linkScreen(Request $request)
    {
        $request->validate([
            'pairing_code' => 'required|string',
            'mac_address'  => 'required|string',
        ]);

        $screen = Screen::where('pairing_code', $request->pairing_code)->first();

        if (!$screen) {
            return response()->json([
                'success' => false,
                'message' => 'كود الربط غير صحيح أو منتهي الصلاحية'
            ], 404);
        }

        // إذا كانت الشاشة مربوطة مسبقاً بجهاز آخر
        if ($screen->mac_address && $screen->mac_address !== $request->mac_address) {
            return response()->json([
                'success' => false,
                'message' => 'هذه الشاشة مربوطة مسبقاً بجهاز آخر'
            ], 400);
        }

        // تحديث بيانات الشاشة
        $screen->update([
            'mac_address' => $request->mac_address,
            'status' => 'Online', // تصبح أونلاين فور الربط
            'linked_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم الربط بنجاح',
            'data' => [
                'screen_id' => $screen->screen_id,
                'screen_name' => $screen->screen_name,
            ]
        ], 200);
    }

    public function check(Request $request)
    {
        $request->validate([
            'mac_address' => 'required|string',
        ]);

        $screen = Screen::where('mac_address', $request->mac_address)->first();

        if (!$screen) {
            return response()->json([
                'success' => false,
                'message' => 'الشاشة غير مسجلة أو غير مربوطة'
            ], 404);
        }

        if ($screen->status === 'pending_activation') {
            return response()->json([
                'success' => false,
                'message' => 'الشاشة بانتظار التفعيل من قبل المدير',
                'data' => [
                    'is_linked' => false
                ]
            ], 200);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'screen_id'   => $screen->screen_id,
                'screen_name' => $screen->screen_name,
                'status'      => $screen->status,
                'is_linked'   => true
            ]
        ], 200);
    }

    // ==========================================
    // 7. إرسال أمر تحكم للشاشة (Remote Command)
    // ==========================================
    public function sendCommand(Request $request)
    {
        // التحقق من الصلاحيات (يجب أن يكون Admin أو Owner للشاشة)
        // إذا كان التطبيق يعتمد على الـ token، يجب وضع هذا المسار داخل مجموعة auth:sanctum
        
        $request->validate([
            'target_screen' => 'required|string', // "all" أو الـ mac_address
            'command'       => 'required|string', // RESTART_APP, SLEEP_SCREEN, WAKE_SCREEN, إلخ
        ]);

        \Illuminate\Support\Facades\DB::table('screen_commands')->insert([
            'target_screen' => $request->target_screen,
            'command'       => $request->command,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إرسال الأمر للشاشة بنجاح وسيتم تنفيذه لحظياً'
        ], 200);
    }

    // ==========================================
    // 7.5 توليد معرف فريد جديد لشاشة (من تطبيق التلفاز)
    // ==========================================
    public function generateId(Request $request)
    {
        // نولد كود فريد مكون من 6 حروف/أرقام مميزة وسهلة القراءة
        $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $generatedId = '';
        do {
            $generatedId = 'SB-';
            for ($i = 0; $i < 6; $i++) {
                $generatedId .= $characters[rand(0, strlen($characters) - 1)];
            }
        } while (Screen::where('mac_address', $generatedId)->exists());

        // ننشئ سجلاً مؤقتاً في الشاشات بحالة 'pending_activation'
        $screen = Screen::create([
            'screen_name' => 'شاشة غير مفعلة',
            'mac_address' => $generatedId,
            'pairing_code'=> $generatedId,
            'status'      => 'pending_activation',
        ]);

        return response()->json([
            'success' => true,
            'device_id' => $generatedId
        ], 200);
    }

    // ==========================================
    // 8. جلب سعة الشاشة الزمنية وأوقات الذروة (للمعلن)
    // ==========================================
    public function getAvailability(Request $request, $id)
    {
        $screen = Screen::find($id);
        if (!$screen) return response()->json(['message' => 'الشاشة غير موجودة'], 404);

        // إذا لم يرسل تاريخ، نعتبره تاريخ اليوم
        $date = $request->query('date', now()->toDateString());
        
        // جلب أوقات الذروة لهذه الشاشة
        $pricingSlots = \App\Models\ScreenPricingSlot::where('screen_id', $id)->get();

        $availability = [];

        for ($hour = 0; $hour < 24; $hour++) {
            $timeString = sprintf('%02d:00:00', $hour);
            $nextTimeString = sprintf('%02d:00:00', $hour + 1);
            if ($hour == 23) $nextTimeString = '23:59:59';

            // هل الوقت في وقت ذروة؟
            $multiplier = 1.0;
            $isPeak = false;
            foreach ($pricingSlots as $slot) {
                if ($timeString >= $slot->start_time && $timeString < $slot->end_time) {
                    $multiplier = $slot->price_multiplier;
                    $isPeak = true;
                    break;
                }
            }

            // حساب الثواني المحجوزة في هذه الساعة المحددة
            $usedSeconds = \App\Models\AdSchedule::whereHas('advertisement', function ($q) {
                    $q->where('status', '!=', 'Rejected')->whereNull('deleted_at');
                })
                ->whereHas('advertisement.screens', function($q) use ($id) {
                    $q->where('screens.screen_id', $id);
                })
                ->where('is_active', 'true')
                ->where('start_date', '<=', $date)
                ->where('end_date', '>=', $date)
                ->where(function ($query) use ($timeString, $nextTimeString) {
                    // تداخل وقت الإعلان مع هذه الساعة
                    $query->where(function($q) use ($timeString, $nextTimeString) {
                        $q->where('start_time', '<', $nextTimeString)
                          ->where('end_time', '>', $timeString);
                    })->orWhereNull('start_time'); // يشمل 24/7
                })
                ->sum('allocated_seconds');

            $availability[] = [
                'hour'              => sprintf('%02d:00', $hour),
                'is_peak'           => $isPeak,
                'price_multiplier'  => (float) $multiplier,
                'available_seconds' => max(0, 3600 - $usedSeconds),
                'is_full'           => $usedSeconds >= 3600,
            ];
        }

        return response()->json([
            'success' => true, 
            'date' => $date,
            'data' => $availability
        ], 200);
    }
}
