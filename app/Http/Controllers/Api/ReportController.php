<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Screen;
use App\Models\Advertisement;
use App\Models\AdScreen;
use App\Models\PlaybackLog;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function screenReport(Request $request)
    {
        $request->validate([
            'screen_id' => 'required|exists:screens,screen_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $screenId = $request->screen_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $screen = Screen::with('street.region.governorate')->findOrFail($screenId);

        $user = $request->user();

        // חماية تقارير الملاك
        if ($user && ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner'))) {
            if ((int) $screen->owner_id !== (int) $user->user_id) {
                return response()->json(['success' => false, 'message' => 'غير مصرح لك بالوصول لتقرير هذه الشاشة'], 403);
            }
        }

        // Fetch ads that are linked to this screen and overlap with the date range
        $ads = Advertisement::with(['advertiser', 'category', 'screens'])
            ->whereHas('screens', function ($q) use ($screenId) {
                $q->where('advertisement_screen.screen_id', $screenId);
            })
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->whereIn('status', ['Active', 'Completed', 'Paused'])
            ->get();

        $totalRevenue = 0;
        $totalAds = $ads->count();
        $totalPlays = 0;

        $adsData = [];

        foreach ($ads as $ad) {
            $screensCount = max($ad->screens->count(), 1);
            $netToOwners = ($ad->total_cost ?? 0) * 0.80; // 80% to owners, 20% to platform
            $revenue = $netToOwners / $screensCount;

            $totalRevenue += $revenue;

            // Get actual plays within the date range
            $playsCount = PlaybackLog::where('ad_id', $ad->ad_id)
                ->where('screen_id', $screenId)
                ->whereDate('played_at', '>=', $startDate)
                ->whereDate('played_at', '<=', $endDate)
                ->count();

            $totalPlays += $playsCount;

            $adsData[] = [
                'ad_id' => $ad->ad_id,
                'title' => $ad->title,
                'advertiser' => $ad->advertiser ? $ad->advertiser->full_name : 'غير محدد',
                'category' => $ad->category ? $ad->category->category_name : 'غير محدد',
                'start_date' => $ad->start_date,
                'end_date' => $ad->end_date,
                'frequency' => $ad->daily_frequency,
                'revenue' => $revenue,
                'plays_count' => $playsCount,
            ];
        }

        return response()->json([
            'screen' => $screen,
            'summary' => [
                'total_ads' => $totalAds,
                'total_revenue' => $totalRevenue,
                'total_plays' => $totalPlays,
            ],
            'ads' => $adsData,
        ]);
    }

    public function maintenanceReport(Request $request)
    {
        $request->validate([
            'screen_id' => 'required|exists:screens,screen_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $screenId = $request->screen_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $screen = Screen::with('street.region.governorate')->findOrFail($screenId);

        // Fetch total ads count just for activity metric, no financial data
        $adsCount = Advertisement::whereHas('screens', function ($q) use ($screenId) {
            $q->where('advertisement_screen.screen_id', $screenId);
        })
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate)
            ->whereIn('status', ['Active', 'Completed', 'Paused'])
            ->count();

        // Count plays to show the screen was actually active and functioning
        $totalPlays = PlaybackLog::where('screen_id', $screenId)
            ->whereDate('played_at', '>=', $startDate)
            ->whereDate('played_at', '<=', $endDate)
            ->count();

        // Calculate hours since disconnected_at if it's currently disconnected
        $offlineHours = 0;
        if ($screen->disconnected_at) {
            $offlineHours = \Carbon\Carbon::parse($screen->disconnected_at)->diffInHours(now());
        }

        return response()->json([
            'screen' => $screen,
            'summary' => [
                'total_ads' => $adsCount,
                'total_plays' => $totalPlays,
                'status' => $screen->status,
                'last_ping' => $screen->linked_at,
                'offline_since' => $screen->disconnected_at,
                'offline_hours' => $offlineHours,
            ]
        ]);
    }
}
