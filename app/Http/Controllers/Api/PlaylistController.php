<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Screen;
use App\Models\Advertisement;
use Illuminate\Support\Facades\Storage;

class PlaylistController extends Controller
{
    /**
     * جلب قائمة التشغيل لشاشة محددة
     */
    public function getPlaylist(Request $request)
    {
        // نستخدم mac_address للتعرف على الشاشة (target)
        $macAddress = $request->query('target');

        if (!$macAddress) {
            return response()->json(['message' => 'target (mac_address) is required'], 400);
        }

        $screen = Screen::where('mac_address', $macAddress)->first();

        if (!$screen) {
            return response()->json(['message' => 'Screen not found or not linked'], 404);
        }

        // جلب الوقت والتاريخ الحالي للسيرفر
        $nowDate = now()->toDateString();
        $nowTime = now()->toTimeString();

        // 💡 خوارزمية Smart Loop Playlist
        // جلب الإعلانات المرتبطة بهذه الشاشة، النشطة، والتي جدولها الزمني يطابق الساعة الحالية فقط!
        $ads = $screen->advertisements()
            ->whereIn('status', ['Active', 'Approved'])
            ->where('advertisements.is_deleted', 0)
            ->whereHas('schedules', function ($q) use ($nowDate, $nowTime) {
                $q->where('is_active', 1)
                  ->where('start_date', '<=', $nowDate)
                  ->where('end_date', '>=', $nowDate);
            })
            ->with(['schedules' => function($q) use ($nowDate, $nowTime) {
                // جلب الجدولة المطابقة لمعرفة خصائصها
                $q->where('start_date', '<=', $nowDate)
                  ->where('end_date', '>=', $nowDate);
            }])
            ->get();

        $playlist = $ads->map(function ($ad) {
            $filePath = $ad->file_path;
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'avi', 'mov']) ? 'video' : 'image';

            // إذا كان هناك جدولة محددة بوقت، نأخذ أول جدولة مطابقة
            $schedule = $ad->schedules->first();

            return [
                'id'               => $ad->ad_id,
                'title'            => $ad->title,
                'url'              => url($filePath), // تم التعديل لأن المسار محفوظ مسبقاً مع /storage/
                'type'             => $type,
                'duration'         => $ad->duration * 1000, // تحويل للـ milliseconds كما يتوقع التطبيق
                'interval_minutes' => $schedule ? $schedule->interval_minutes : 1, // كل كم دقيقة يعرض
                'allocated_seconds'=> $schedule ? $schedule->allocated_seconds : $ad->duration,
                'starts_at'        => $schedule ? $schedule->start_time : null,
                'expires_at'       => $schedule ? $schedule->end_time : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $playlist
        ], 200);
    }
}
