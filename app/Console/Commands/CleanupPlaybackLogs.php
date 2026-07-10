<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PlaybackLog;
use Carbon\Carbon;

class CleanupPlaybackLogs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'logs:cleanup {--days=30 : The number of days to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up playback logs older than a specific number of days to save database space';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = $this->option('days');
        
        if (!is_numeric($days) || $days <= 0) {
            $this->error("Invalid days parameter. Must be greater than 0.");
            return;
        }

        $dateThreshold = Carbon::now()->subDays($days);
        
        $this->info("Cleaning up playback logs older than {$days} days ({$dateThreshold->toDateString()})...");

        // Using chunking or direct delete depending on DB size. Direct delete is faster but might lock table.
        // We will use direct delete since it's a simple query on an indexed column (hopefully played_at is indexed).
        $deletedCount = PlaybackLog::where('played_at', '<', $dateThreshold)->delete();

        $this->info("Successfully deleted {$deletedCount} old playback logs.");
    }
}
