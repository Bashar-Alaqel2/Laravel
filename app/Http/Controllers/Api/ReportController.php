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
}
