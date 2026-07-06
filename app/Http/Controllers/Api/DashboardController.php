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
        // نحاول جلب آخر 5 سجلات من القاعدة
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

