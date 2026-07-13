<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Screen;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckScreenDowntime extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'screens:check-downtime';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for screens that have gone offline and interrupt their ads';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Find screens that haven't pinged in 60 minutes and don't have disconnected_at set.
        $cutoffTime = now()->subMinutes(60);

        $offlineScreens = Screen::whereNotNull('linked_at')
            ->where('linked_at', '<=', $cutoffTime)
            ->whereNull('disconnected_at')
            ->get();

        if ($offlineScreens->isEmpty()) {
            return; // Nothing to do
        }

        foreach ($offlineScreens as $screen) {
            // Set disconnected_at to the exact time we marked it offline
            $screen->update(['disconnected_at' => now()]);

            // Find Active ads on this screen
            $activeAds = $screen->advertisements()
                ->whereIn('status', ['Active', 'active'])
                ->where('advertisements.is_deleted', 0)
                ->get();

            foreach ($activeAds as $ad) {
                // Change status to interrupted, with a special rejection_reason so we know the system did it.
                $ad->update([
                    'status' => 'interrupted',
                    'rejection_reason' => 'system_offline_interruption'
                ]);
                $this->info("Interrupted Ad ID: {$ad->ad_id} due to Screen ID: {$screen->screen_id} going offline.");
            }

            Log::info("Screen ID {$screen->screen_id} marked as offline and ads interrupted.");
        }
    }
}
