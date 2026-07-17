<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Screen;
use App\Models\Advertisement;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

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

        // كاش ذكي: يتم تحديث القائمة كل 60 ثانية لكل شاشة
        // مفتاح الكاش يتضمن الساعة الحالية لضمان التحديث عند تغير الساعة
        $currentHour = now()->format('Y-m-d-H');
        $cacheKey = "playlist_{$macAddress}_{$currentHour}";

        $playlist = Cache::remember($cacheKey, 60, function () use ($screen) {
            $nowDate = now()->toDateString();
            $nowTime = now()->toTimeString();

            // 💡 خوارزمية Smart Loop Playlist
            $ads = $screen->advertisements()
                ->whereIn('status', ['Active', 'Approved'])
                ->where('advertisements.is_deleted', 0)
                ->whereHas('schedules', function ($q) use ($nowDate, $nowTime) {
                    $q->where('is_active', 1)
                      ->where('start_date', '<=', $nowDate)
                      ->where('end_date', '>=', $nowDate)
                      ->where(function ($subQ) use ($nowTime) {
                          $subQ->whereNull('end_time')
                               ->orWhere('end_time', '>=', $nowTime);
                      });
                })
                ->with(['schedules' => function($q) use ($nowDate) {
                    $q->where('start_date', '<=', $nowDate)
                      ->where('end_date', '>=', $nowDate);
                }])
                ->get();

            return $ads->map(function ($ad) use ($nowTime) {
                $filePath = $ad->file_path;
                $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                $type = in_array($extension, ['mp4', 'avi', 'mov']) ? 'video' : 'image';

                $schedule = $ad->schedules->first();
                
                $startsAt = $schedule ? $schedule->start_time : null;
                $expiresAt = $schedule ? $schedule->end_time : null;

                // 🚀 إذا وقت البداية مر بالفعل، نعرضه فوراً
                if ($startsAt && $nowTime >= $startsAt) {
                    $startsAt = null;
                }

                return [
                    'id'               => $ad->ad_id,
                    'title'            => $ad->title,
                    'url'              => url($filePath),
                    'type'             => $type,
                    'duration'         => $ad->duration * 1000,
                    'interval_minutes' => $schedule ? $schedule->interval_minutes : 1,
                    'allocated_seconds'=> $schedule ? $schedule->allocated_seconds : $ad->duration,
                    'starts_at'        => $startsAt,
                    'expires_at'       => $expiresAt,
                ];
            })->values();
        });

        return response()->json([
            'success' => true,
            'data'    => $playlist
        ], 200);
    }
}

