<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Advertisement;
use App\Models\Screen;
use App\Models\User;
use App\Models\Invoice;
use App\Models\PlaybackLog;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * جلب إحصائيات لوحة التحكم الشاملة (Dashboard Overview)
     * مخصصة لتلبية متطلبات واجهة النظام والإدارة
     */
    public function getOverview(Request $request)
    {
        // 1. حساب بطاقات المؤشرات (KPI Cards)
        $totalRevenue = \App\Models\FinancialLedger::where('transaction_type', 'payment_in')
                                                  ->where('status', 'completed')
                                                  ->sum('amount') ?? 0;
        $activeScreensCount = Screen::whereIn('status', ['active', 'Online', 'online'])->count() ?? 0;
        $totalScreensCount = Screen::count() ?? 0;
        $pendingAdsCount = Advertisement::where('status', 'pending')->count() ?? 0;
        $activeUsersCount = User::count() ?? 0;

        // وضع بيانات افتراضية إذا كانت قاعدة البيانات فارغة (لكي لا تتعطل واجهة علي)
        if ($totalScreensCount == 0) {
            $totalRevenue = 20530;
            $activeScreensCount = 500;
            $totalScreensCount = 700;
            $pendingAdsCount = 23;
            $activeUsersCount = 13;
        }

        // 2. إحصائيات الدخل الأسبوعي (Weekly Revenue)
        $weeklyRevenue = [];
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        foreach ($days as $index => $dayName) {
            // نولد بيانات افتراضية لاختبار الواجهة
            $weeklyRevenue[] = [
                'day' => $dayName,
                'amount' => rand(500, 2000), // يمكنك لاحقاً تبديلها باستعلامات حقيقية
            ];
        }

        // 3. التوزيع الجغرافي للشاشات (Screens by Governorate)
        $screensByGov = [
            ['name' => 'Sanaa', 'count' => 45],
            ['name' => 'Aden', 'count' => 30],
            ['name' => 'Taiz', 'count' => 25],
        ];

        // 4. السجلات الحديثة (Recent Play Logs)
        // نحاول جلب آخر 5 سجلات من القاعدة
        $recentLogsQuery = PlaybackLog::with(['advertisement', 'screen'])
                        ->orderBy('played_at', 'desc')
                        ->take(5)
                        ->get();
        
        $recentLogs = $recentLogsQuery->map(function($log) {
            return [
                'ad_name' => $log->advertisement->title ?? 'Unknown Ad',
                'screen_name' => $log->screen->name ?? 'Unknown Screen',
                'duration' => $log->duration_seconds,
                'playback_timestamp' => $log->played_at,
            ];
        })->toArray();

        // بيانات تجريبية في حال كانت القاعدة فارغة
        if (empty($recentLogs)) {
            $recentLogs = [
                ['ad_name' => 'Ad 1 (Pepsi)', 'screen_name' => 'Screen 1 (Sanaa Univ)', 'duration' => 15, 'playback_timestamp' => Carbon::now()->subMinutes(2)->toDateTimeString()],
                ['ad_name' => 'Ad 2 (MTN)', 'screen_name' => 'Screen 5 (Tahrir Sq)', 'duration' => 10, 'playback_timestamp' => Carbon::now()->subMinutes(15)->toDateTimeString()],
                ['ad_name' => 'Ad 3 (Samsung)', 'screen_name' => 'Screen 12 (Aden Mall)', 'duration' => 30, 'playback_timestamp' => Carbon::now()->subMinutes(45)->toDateTimeString()],
            ];
        }

        // إرجاع الاستجابة بصيغة JSON صديقة للواجهة الأمامية
        return response()->json([
            'status' => 'success',
            'data' => [
                'kpis' => [
                    'total_revenue' => $totalRevenue,
                    'active_screens' => $activeScreensCount,
                    'total_screens' => $totalScreensCount,
                    'pending_ads' => $pendingAdsCount,
                    'active_users' => $activeUsersCount,
                ],
                'charts' => [
                    'weekly_revenue' => $weeklyRevenue,
                    'screens_by_governorate' => $screensByGov,
                ],
                'recent_logs' => $recentLogs
            ]
        ]);
    }

    /**
     * جلب إحصائيات لوحة التحكم الخاصة بمالك الشاشة (Screen Owner Dashboard)
     */
    public function getOwnerOverview(Request $request)
    {
        $user = $request->user();
        $userId = $user->user_id;

        // 1. حساب أرباح اليوم
        $todayEarnings = \App\Models\FinancialLedger::where('user_id', $userId)
            ->where('transaction_type', 'payout_pending')
            ->whereDate('created_at', Carbon::today())
            ->sum('amount') ?? 0;

        // 2. حساب الشاشات النشطة والإجمالي
        $screens = Screen::with(['street.region.governorate'])->where('owner_id', $userId)->get();
        
        $activeScreensCount = 0;
        foreach ($screens as $screen) {
            if ($screen->status === 'Online' || $screen->status === 'online' || $screen->status === 'active') {
                $activeScreensCount++;
            }
        }
        $totalScreensCount = $screens->count();

        // 3. حساب إجمالي الحملات الإعلانية على شاشاته
        $totalCampaigns = \DB::table('ad_screens')
            ->join('screens', 'ad_screens.screen_id', '=', 'screens.screen_id')
            ->join('advertisements', 'ad_screens.ad_id', '=', 'advertisements.ad_id')
            ->where('screens.owner_id', $userId)
            ->whereRaw('advertisements.is_deleted = false')
            ->distinct('ad_screens.ad_id')
            ->count();

        // 4. مشاهدات الأسبوع (Playback Logs)
        $weeklyViews = PlaybackLog::join('screens', 'playback_logs.screen_id', '=', 'screens.screen_id')
            ->where('screens.owner_id', $userId)
            ->where('playback_logs.played_at', '>=', Carbon::now()->subDays(7))
            ->count();

        // 5. الأنشطة المالية الأخيرة
        $activities = \App\Models\FinancialLedger::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(function($ledger) {
                $isEarnings = in_array($ledger->transaction_type, ['payout_pending', 'payment_in']);
                return [
                    'title' => $ledger->notes ?? ($isEarnings ? 'أرباح مستحقة' : 'سحب أرباح'),
                    'amount' => ($isEarnings ? '+' : '-') . number_format($ledger->amount, 0) . ' ر.ي',
                    'time' => Carbon::parse($ledger->created_at)->format('g:i a'),
                    'isEarnings' => $isEarnings
                ];
            });

        // 6. قائمة شاشاتي بصيغة مناسبة للواجهة الأمامية
        $screensData = $screens->map(function($screen) {
            return [
                'name' => $screen->screen_name,
                'location_details' => ($screen->street->street_name ?? '') . ' - ' . ($screen->street->region->region_name ?? ''),
                'city' => ($screen->street->region->governorate->governorate_name ?? 'مأرب') . ' - Yemen',
                'status' => ($screen->status === 'Online' || $screen->status === 'online' || $screen->status === 'active') ? 'نشطة' : 'غير متصلة',
                'image_base64' => $screen->image_path, // مسار الصورة (Base64 أو رابط)
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'kpis' => [
                    'today_earnings' => number_format($todayEarnings, 0) . ' ر.ي',
                    'active_screens' => "{$activeScreensCount} / {$totalScreensCount}",
                    'total_campaigns' => (string)$totalCampaigns,
                    'weekly_views' => $weeklyViews > 1000 ? number_format($weeklyViews / 1000, 1) . 'K+' : (string)$weeklyViews,
                ],
                'screens' => $screensData,
                'financial_activities' => $activities
            ]
        ]);
    }
}

