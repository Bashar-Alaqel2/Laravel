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
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    /**
     * جلب إحصائيات لوحة السكرتير
     */
    public function getSecretaryOverview(Request $request)
    {
        $data = Cache::remember('secretary_dashboard_overview', 300, function () {
            // 1. حساب الإحصائيات الخاصة بالعمليات فقط
            $pendingAdsCount = Advertisement::where('status', 'pending')->count() ?? 0;
            
            $pendingPaymentsCount = \App\Models\FinancialLedger::where('transaction_type', 'payment_pending')
                                                      ->where('status', 'pending')
                                                      ->count() ?? 0;

            $offlineScreensCount = Screen::whereIn('status', ['offline', 'Offline', 'maintenance', 'error'])->count() ?? 0;
            
            $totalAds = Advertisement::where('is_deleted', 0)->count() ?? 0;

            return [
                'pending_ads_count' => $pendingAdsCount,
                'pending_payments_count' => $pendingPaymentsCount,
                'offline_screens_count' => $offlineScreensCount,
                'total_ads' => $totalAds,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    /**
     * جلب إحصائيات لوحة التحكم الشاملة (Dashboard Overview)
     * مخصصة لتلبية متطلبات واجهة النظام والإدارة
     */
    public function getOverview(Request $request)
    {
        $data = Cache::remember('admin_dashboard_overview', 300, function () {
            // 1. حساب بطاقات المؤشرات (KPI Cards)
            $totalRevenue = \App\Models\FinancialLedger::where('transaction_type', 'payment_in')
                                                      ->where('status', 'completed')
                                                      ->sum('amount') ?? 0;
            $activeScreensCount = Screen::whereIn('status', ['active', 'Online', 'online'])->count() ?? 0;
            $totalScreensCount = Screen::count() ?? 0;
            $pendingAdsCount = Advertisement::where('status', 'pending')->count() ?? 0;
            $activeUsersCount = User::count() ?? 0;

            // 2. إحصائيات الدخل الأسبوعي (Weekly Revenue)
            $weeklyRevenue = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayName = $date->format('D');
                
                $amount = \App\Models\FinancialLedger::where('transaction_type', 'payment_in')
                    ->where('status', 'completed')
                    ->whereDate('created_at', $date->toDateString())
                    ->sum('amount') ?? 0;
                    
                $weeklyRevenue[] = [
                    'day' => $dayName,
                    'amount' => $amount,
                ];
            }

            // 3. التوزيع الجغرافي للشاشات (Screens by Governorate)
            $govData = \App\Models\Screen::join('streets', 'screens.street_id', '=', 'streets.street_id')
                ->join('regions', 'streets.region_id', '=', 'regions.region_id')
                ->join('governorates', 'regions.gov_id', '=', 'governorates.gov_id')
                ->select('governorates.name as name', \DB::raw('count(screens.screen_id) as count'))
                ->groupBy('governorates.name')
                ->get();
                
            $screensByGov = $govData->isEmpty() ? [] : $govData->toArray();

            // 4. السجلات الحديثة (Recent Play Logs)
            $recentLogsQuery = PlaybackLog::with(['advertisement', 'screen'])
                            ->orderBy('played_at', 'desc')
                            ->take(5)
                            ->get();
            
            $recentLogs = $recentLogsQuery->map(function($log) {
                return [
                    'ad_name' => $log->advertisement->title ?? 'Unknown Ad',
                    'screen_name' => $log->screen->screen_name ?? 'Unknown Screen',
                    'duration' => $log->advertisement->duration ?? null,
                    'playback_timestamp' => $log->played_at,
                ];
            })->toArray();

            return [
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
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $data
        ]);
    }

    /**
     * جلب إحصائيات لوحة التحكم الخاصة بمالك الشاشة (Screen Owner Dashboard)
     */
    public function getOwnerOverview(Request $request)
    {
        $user = $request->user();
        $userId = $user->user_id;

        $data = Cache::remember("owner_dashboard_{$userId}", 300, function () use ($userId) {
            // 1. حساب أرباح اليوم
            $todayEarnings = \App\Models\FinancialLedger::where('user_id', $userId)
                ->where('transaction_type', 'payout_pending')
                ->whereDate('created_at', Carbon::today())
                ->sum('amount') ?? 0;

            // حساب أرباح الشهر
            $monthlyEarnings = \App\Models\FinancialLedger::where('user_id', $userId)
                ->where('transaction_type', 'payout_pending')
                ->whereMonth('created_at', Carbon::now()->month)
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
                ->whereRaw('advertisements.is_deleted = 0')
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
                    return [
                        'id' => $ledger->ledger_id,
                        'text' => $ledger->notes ?? 'معاملة مالية',
                        'type' => $ledger->amount > 0 ? 'success' : 'warning',
                        'time' => $ledger->created_at->diffForHumans()
                    ];
                });

            // 6. قائمة شاشاتي بصيغة مناسبة للواجهة الأمامية
            $screensData = $screens->map(function($screen) {
                return [
                    'id' => $screen->screen_id,
                    'name' => $screen->screen_name,
                    'status' => $screen->status,
                    'revenue' => \App\Models\FinancialLedger::where('user_id', $screen->owner_id)
                                    ->where('transaction_type', 'payout_pending')
                                    ->where('notes', 'like', '%' . $screen->screen_name . '%')
                                    ->sum('amount') ?? 0
                ];
            });

            // 6. الدخل الأسبوعي للرسم البياني
            $weeklyRevenue = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dayName = $date->format('D');
                
                $amount = \App\Models\FinancialLedger::where('user_id', $userId)
                    ->where('transaction_type', 'payout_pending')
                    ->whereDate('created_at', $date->toDateString())
                    ->sum('amount') ?? 0;
                    
                $weeklyRevenue[] = [
                    'day' => $dayName,
                    'amount' => $amount,
                ];
            }

            return [
                'kpis' => [
                    'today_earnings' => $todayEarnings,
                    'monthly_earnings' => $monthlyEarnings,
                    'active_screens' => $activeScreensCount,
                    'total_screens' => $totalScreensCount,
                    'total_campaigns' => $totalCampaigns,
                    'weekly_views' => $weeklyViews,
                ],
                'charts' => [
                    'weekly_revenue' => $weeklyRevenue
                ],
                'screens' => $screensData,
                'financial_activities' => $activities
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}

