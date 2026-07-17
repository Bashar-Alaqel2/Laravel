<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Screen;
use App\Models\Advertisement;
use App\Models\FinancialLedger;
use App\Models\PlaybackLog;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function ownerAnalytics(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['success' => false], 401);

        $screens = Screen::with('street.region.governorate')
            ->where('owner_id', $user->user_id)
            ->get();

        $analytics = [];
        $totalRevenue = 0;
        $totalImpressions = 0;

        foreach ($screens as $screen) {
            $screenRevenue = \App\Models\FinancialLedger::where('user_id', $user->user_id)
                ->where('transaction_type', 'payout_pending') // Approximate revenue for this screen
                ->where('notes', 'like', '%'.$screen->screen_name.'%')
                ->sum('amount') ?? 0;
                
            $screenImpressions = \App\Models\PlaybackLog::where('screen_id', $screen->screen_id)->count();

            $totalRevenue += $screenRevenue;
            $totalImpressions += $screenImpressions;

            $analytics[] = [
                'screen_id' => $screen->screen_id,
                'screen_name' => $screen->screen_name,
                'location' => $screen->street ? $screen->street->street_name . ', ' . ($screen->street->region->region_name ?? '') : 'غير محدد',
                'status' => $screen->computed_status,
                'fill_rate' => 0,
                'impressions' => $screenImpressions,
                'revenue' => $screenRevenue
            ];
        }

        return response()->json([
            'success' => true,
            'screens' => $analytics,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_impressions' => $totalImpressions,
                'total_screens' => count($screens),
                'online_screens' => $screens->filter(fn($s) => $s->computed_status === 'Online')->count(),
            ]
        ], 200);
    }

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

        // حماية تقارير الملاك
        if ($user && ($user->role_id === 8 || ($user->hasRole(\App\Models\Role::SCREEN_OWNER)))) {
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
                'status' => $screen->computed_status,
                'last_ping' => $screen->linked_at,
                'offline_since' => $screen->disconnected_at,
                'offline_hours' => $offlineHours,
            ]
        ]);
    }
}

