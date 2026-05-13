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
        $activeScreensCount = Screen::where('status', 'active')->count() ?? 0;
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
}
