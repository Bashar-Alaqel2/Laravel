<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\PlaybackLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Response;

class PlaybackLogController extends Controller
{
    /**
     * Get paginated and filtered playback logs
     */
    public function index(Request $request)
    {
        $query = $this->buildQuery($request);
        
        $perPage = $request->input('per_page', 20);
        $logs = $query->paginate($perPage);

        // Map data to match what frontend expects
        $mappedLogs = $logs->getCollection()->map(function($log) {
            return [
                'log_id' => $log->log_id,
                'ad_name' => $log->advertisement->title ?? 'Unknown Ad',
                'screen_name' => $log->screen->screen_name ?? 'Unknown Screen',
                'duration' => $log->advertisement->duration ?? 15, // Fallback to 15s
                'played_at' => Carbon::parse($log->played_at)->format('Y-m-d H:i:s'),
                'played_at_human' => Carbon::parse($log->played_at)->diffForHumans(),
            ];
        });

        // Compute Quick Stats
        $stats = [
            'total_plays' => $logs->total(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $mappedLogs,
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total()
                ],
                'stats' => $stats
            ]
        ]);
    }

    /**
     * Export playback logs to CSV
     */
    public function export(Request $request)
    {
        $query = $this->buildQuery($request);
        $logs = $query->get();

        $headers = [
            "Content-type"        => "text/csv; charset=UTF-8",
            "Content-Disposition" => "attachment; filename=playback_logs_" . date('Y-m-d_H-i-s') . ".csv",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use ($logs) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for proper UTF-8 handling in Excel
            fputs($file, "\xEF\xBB\xBF");
            
            fputcsv($file, ['ID', 'Ad Name', 'Screen Name', 'Played At', 'Duration (Seconds)']);

            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->log_id,
                    $log->advertisement->title ?? 'Unknown Ad',
                    $log->screen->screen_name ?? 'Unknown Screen',
                    $log->played_at,
                    $log->advertisement->duration ?? 15
                ]);
            }

            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Helper to build the query based on request filters
     */
    private function buildQuery(Request $request)
    {
        $query = PlaybackLog::with(['advertisement', 'screen']);

        // Handle permissions
        $user = $request->user();
        if ($user && $user->role && $user->role->role_name === 'ScreenOwner') {
            $query->whereHas('screen', function($q) use ($user) {
                $q->where('owner_id', $user->user_id);
            });
        } elseif ($user && $user->role && $user->role->role_name === 'Advertiser') {
            $query->whereHas('advertisement', function($q) use ($user) {
                $q->where('advertiser_id', $user->user_id);
            });
        }

        if ($request->filled('screen_id')) {
            $query->where('screen_id', $request->screen_id);
        }

        if ($request->filled('ad_id')) {
            $query->where('ad_id', $request->ad_id);
        }

        if ($request->filled('start_date') && $request->filled('end_date')) {
            // Include end date fully
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->whereBetween('played_at', [$request->start_date, $endDate]);
        } elseif ($request->filled('start_date')) {
            $query->where('played_at', '>=', $request->start_date);
        } elseif ($request->filled('end_date')) {
            $endDate = Carbon::parse($request->end_date)->endOfDay();
            $query->where('played_at', '<=', $endDate);
        }

        $query->orderBy('played_at', 'desc');

        return $query;
    }

    /**
     * Manual cleanup of old logs via API
     */
    public function cleanup(Request $request)
    {
        $days = $request->input('days', 30);
        $dateThreshold = Carbon::now()->subDays($days);
        
        $deletedCount = PlaybackLog::where('played_at', '<', $dateThreshold)->delete();

        return response()->json([
            'success' => true,
            'message' => "تم حذف {$deletedCount} سجل قديم بنجاح.",
            'deleted_count' => $deletedCount
        ]);
    }
}
