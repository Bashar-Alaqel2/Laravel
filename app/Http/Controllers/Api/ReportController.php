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

    public function comprehensiveFinancial(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role_id, [1, 2, 7])) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        // Total Revenue (all money in)
        $totalRevenue = FinancialLedger::where('transaction_type', 'payment_in')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', 'completed')
            ->sum('amount');

        // Platform Commission
        $platformCommission = FinancialLedger::where('transaction_type', 'platform_fee')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', 'completed')
            ->sum('amount');

        // Net to Owners (payout_pending + payout_completed)
        $ownersNetProfit = FinancialLedger::whereIn('transaction_type', ['payout_pending', 'payout_completed'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        // Status Counts from Ads (to reflect operations)
        $adsCount = [
            'pending' => Advertisement::where('status', 'Pending')
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count(),
            'completed' => Advertisement::whereIn('status', ['Completed', 'Active'])
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count(),
            'rejected' => Advertisement::where('status', 'Rejected')
                ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])->count(),
        ];

        // Monthly Revenue Chart Data
        $monthlyRevenueRaw = FinancialLedger::where('transaction_type', 'payment_in')
            ->where('status', 'completed')
            ->whereYear('created_at', now()->year)
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('SUM(amount) as total'))
            ->groupBy('month')
            ->pluck('total', 'month')->toArray();
        
        $monthlyRevenue = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyRevenue[] = [
                'name' => now()->month($i)->translatedFormat('M'),
                'revenue' => $monthlyRevenueRaw[$i] ?? 0
            ];
        }

        // Top 10 Advertisers by Spend
        $topAdvertisers = FinancialLedger::where('transaction_type', 'payment_in')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('user_id', DB::raw('SUM(amount) as total_spend'))
            ->groupBy('user_id')
            ->orderBy('total_spend', 'desc')
            ->take(10)
            ->with('user')
            ->get()
            ->map(function ($item) {
                return [
                    'advertiser_name' => $item->user ? $item->user->full_name : 'غير محدد',
                    'total_spend' => $item->total_spend
                ];
            });

        // Top 10 Screens by Revenue
        $topScreens = FinancialLedger::whereIn('transaction_type', ['payout_pending', 'payout_completed'])
            ->whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->whereNotNull('screen_id')
            ->select('screen_id', DB::raw('SUM(amount) as total_revenue'))
            ->groupBy('screen_id')
            ->orderBy('total_revenue', 'desc')
            ->take(10)
            ->with('screen')
            ->get()
            ->map(function ($item) {
                return [
                    'screen_name' => $item->screen ? $item->screen->screen_name : 'غير محدد',
                    'total_revenue' => $item->total_revenue
                ];
            });

        return response()->json([
            'success' => true,
            'summary' => [
                'total_revenue' => round($totalRevenue, 2),
                'platform_commission' => round($platformCommission, 2),
                'owners_net_profit' => round($ownersNetProfit, 2),
                'operations_count' => $adsCount,
            ],
            'monthly_revenue' => $monthlyRevenue,
            'top_advertisers' => $topAdvertisers,
            'top_screens' => $topScreens,
        ]);
    }

    public function adPerformance(Request $request)
    {
        $user = $request->user();
        if (!$user || !in_array($user->role_id, [1, 2, 7])) {
            return response()->json(['success' => false, 'message' => 'غير مصرح'], 403);
        }

        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->endOfMonth()->toDateString());

        $adsQuery = Advertisement::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        // Ads by Status
        $adsByStatus = [
            'active' => (clone $adsQuery)->where('status', 'Active')->count(),
            'pending' => (clone $adsQuery)->where('status', 'Pending')->count(),
            'rejected' => (clone $adsQuery)->where('status', 'Rejected')->count(),
            'completed' => (clone $adsQuery)->where('status', 'Completed')->count(),
        ];

        // Average Campaign Duration
        $avgDurationRaw = (clone $adsQuery)->select(DB::raw('AVG(DATEDIFF(end_date, start_date)) as avg_days'))->first();
        $avgCampaignDuration = $avgDurationRaw ? round($avgDurationRaw->avg_days) : 0;

        // Most Expensive Ads
        $topAds = (clone $adsQuery)->orderBy('total_cost', 'desc')
            ->take(5)
            ->get(['ad_id', 'title', 'total_cost', 'status'])
            ->map(function ($ad) {
                return [
                    'id' => $ad->ad_id,
                    'title' => $ad->title,
                    'cost' => $ad->total_cost,
                    'status' => $ad->status,
                ];
            });

        // Ads Distribution by Governorate
        $adsByGov = DB::table('advertisement_screen')
            ->join('advertisements', 'advertisement_screen.ad_id', '=', 'advertisements.ad_id')
            ->join('screens', 'advertisement_screen.screen_id', '=', 'screens.screen_id')
            ->join('streets', 'screens.street_id', '=', 'streets.street_id')
            ->join('regions', 'streets.region_id', '=', 'regions.region_id')
            ->join('governorates', 'regions.governorate_id', '=', 'governorates.governorate_id')
            ->whereBetween('advertisements.created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59'])
            ->select('governorates.governorate_name as name', DB::raw('COUNT(DISTINCT advertisements.ad_id) as value'))
            ->groupBy('governorates.governorate_id', 'governorates.governorate_name')
            ->get();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_ads' => (clone $adsQuery)->count(),
                'avg_duration_days' => $avgCampaignDuration,
                'approval_rate' => $adsByStatus['active'] + $adsByStatus['completed'],
                'rejection_rate' => $adsByStatus['rejected'],
            ],
            'ads_by_status' => $adsByStatus,
            'most_expensive_ads' => $topAds,
            'distribution_by_governorate' => $adsByGov,
        ]);
    }
}
