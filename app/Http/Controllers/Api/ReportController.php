<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Screen;
use App\Models\Advertisement;
use App\Models\AdScreen;
use App\Models\PlaybackLog;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller {
    public function screenReport(Request $request) {
        $request->validate([
            'screen_id' => 'required|exists:screens,screen_id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $screenId = $request->screen_id;
        $startDate = $request->start_date;
        $endDate = $request->end_date;

        $screen = Screen::with('street.region.governorate')->findOrFail($screenId);

        // Fetch ads that are linked to this screen and overlap with the date range
        $adScreens = AdScreen::with(['advertisement.advertiser', 'advertisement.category'])
            ->where('screen_id', $screenId)
            ->whereHas('advertisement', function ($q) use ($startDate, $endDate) {
                $q->where('start_date', '<=', $endDate)
                  ->where('end_date', '>=', $startDate)
                  ->whereIn('status', ['Active', 'Completed', 'Paused']);
            })
            ->get();

        $totalRevenue = 0;
        $totalAds = $adScreens->count();
        $totalPlays = 0;

        $adsData = [];

        foreach ($adScreens as $adScreen) {
            $ad = $adScreen->advertisement;
            if (!$ad) continue;

            $revenue = $adScreen->price ?? 0;
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

    public function networkReport(Request $request) {
        $user = $request->user();

        // 1. Fetch screens
        $query = Screen::with(['street.region.governorate']);
        
        // Filter by owner if user is ScreenOwner
        if ($user && ($user->role_id === 8 || ($user->role && $user->role->role_name === 'ScreenOwner'))) {
            $query->where('owner_id', $user->user_id);
        }
        
        $screens = $query->get();
        
        $totalMonthlyProfits = 0;
        $activeAdsCount = 0;

        $tableData = [];
        $activeAdsMap = [];
        
        // Calculate the start and end of the current week for the chart
        $startOfWeek = now()->startOfWeek();
        $endOfWeek = now()->endOfWeek();
        
        $weeklyProfits = [
            'السبت' => 0, 'الأحد' => 0, 'الإثنين' => 0, 'الثلاثاء' => 0, 'الأربعاء' => 0, 'الخميس' => 0, 'الجمعة' => 0
        ];
        
        $dayMap = [
            'Saturday' => 'السبت', 'Sunday' => 'الأحد', 'Monday' => 'الإثنين', 'Tuesday' => 'الثلاثاء', 
            'Wednesday' => 'الأربعاء', 'Thursday' => 'الخميس', 'Friday' => 'الجمعة'
        ];

        foreach ($screens as $screen) {
            // Find current active ad (simplification: pick the first active ad linked to this screen)
            $activeAdScreen = AdScreen::with('advertisement')
                ->where('screen_id', $screen->screen_id)
                ->whereHas('advertisement', function($q) {
                    $q->where('status', 'Active')
                      ->where('start_date', '<=', now())
                      ->where('end_date', '>=', now());
                })->first();

            $currentAdName = $activeAdScreen ? $activeAdScreen->advertisement->title : 'لا يوجد إعلان حالي';
            
            if ($activeAdScreen) {
                $activeAdsCount++;
                $adTitle = $activeAdScreen->advertisement->title;
                if (!isset($activeAdsMap[$adTitle])) {
                    $activeAdsMap[$adTitle] = 0;
                }
                $activeAdsMap[$adTitle]++;
            }

            // Total Playtime Calculation (mock or based on playback logs)
            // Here we calculate based on logs for this month
            $logsCount = PlaybackLog::where('screen_id', $screen->screen_id)
                ->whereMonth('played_at', now()->month)
                ->count();
            
            // Assume each play is 15 seconds, so logsCount * 15 / 60 = minutes
            $totalPlaytimeMinutes = round(($logsCount * 15) / 60);

            // Generated Profits (Sum of ad_screen prices for this screen)
            $profits = AdScreen::where('screen_id', $screen->screen_id)->sum('price') ?? 0;
            $totalMonthlyProfits += $profits;

            $location = 'غير محدد';
            if ($screen->street && $screen->street->region && $screen->street->region->governorate) {
                 $location = $screen->street->street_name . '، ' . $screen->street->region->governorate->governorate_name;
            }

            $tableData[] = [
                'screen_id' => $screen->screen_id,
                'mac_address' => $screen->mac_address ?? 'غير متوفر',
                'location' => $location,
                'status' => 'متصل', // This could be dynamic based on ping status
                'current_ad' => $currentAdName,
                'total_playtime' => $totalPlaytimeMinutes,
                'generated_profits' => $profits,
            ];
            
            // Weekly profits mock calculation (in a real scenario, sum payments or logs per day)
            // We'll distribute the profit randomly across the week just for demonstration of the chart
            $weeklyProfits['الإثنين'] += $profits * 0.1;
            $weeklyProfits['الثلاثاء'] += $profits * 0.15;
            $weeklyProfits['الأربعاء'] += $profits * 0.2;
            $weeklyProfits['الخميس'] += $profits * 0.25;
            $weeklyProfits['الجمعة'] += $profits * 0.3;
        }

        // Prepare pie chart data
        $pieChartData = [];
        foreach ($activeAdsMap as $name => $count) {
            $pieChartData[] = [
                'name' => $name,
                'value' => $count
            ];
        }
        
        // If no active ads, provide a default empty slice
        if (empty($pieChartData)) {
            $pieChartData[] = ['name' => 'لا توجد إعلانات', 'value' => 1];
        }

        // Prepare line chart data
        $lineChartData = [];
        foreach ($weeklyProfits as $day => $profit) {
            $lineChartData[] = [
                'day' => $day,
                'profit' => round($profit, 2)
            ];
        }

        return response()->json([
            'table_data' => $tableData,
            'summary' => [
                'total_screens' => count($screens),
                'total_active_ads' => $activeAdsCount,
                'total_monthly_profits' => round($totalMonthlyProfits, 2),
                'personal_profits' => round($totalMonthlyProfits * 0.7, 2), // Assuming 70% share for screen owner if applicable
            ],
            'charts' => [
                'pie' => $pieChartData,
                'line' => $lineChartData
            ]
        ]);
    }
}
