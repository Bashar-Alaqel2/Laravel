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

        // جلب الإعلانات المرتبطة بهذه الشاشة والتي حالتها Approved
        // ملاحظة: يمكنك لاحقاً إضافة شروط الوقت (Schedules)
        $ads = $screen->advertisements()->where('status', 'Approved')->get();

        $playlist = $ads->map(function ($ad) {
            $filePath = $ad->file_path;
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            $type = in_array($extension, ['mp4', 'avi', 'mov']) ? 'video' : 'image';

            return [
                'id'         => $ad->ad_id,
                'title'      => $ad->title,
                'url'        => url(Storage::url($filePath)),
                'type'       => $type,
                'duration'   => $ad->duration * 1000, // تحويل للـ milliseconds كما يتوقع التطبيق
                'starts_at'  => null, // يمكن إضافة الجدولة هنا لاحقاً
                'expires_at' => null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $playlist
        ], 200);
    }
}
