<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Advertisement;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanDeletedAdFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ads:clean-deleted-files {--days=30}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes video/image files for advertisements that were deleted more than X days ago to free up server space.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        $dateThreshold = Carbon::now()->subDays($days);

        $adsToClean = Advertisement::where('is_deleted', 'true')
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $dateThreshold)
            ->whereNull('file_deleted_at')
            ->get();

        $count = 0;
        $savedBytes = 0;

        foreach ($adsToClean as $ad) {
            // Delete the file from storage if it exists
            if (!empty($ad->file_path)) {
                $path = str_replace('/storage/', '', $ad->file_path);
                if (Storage::disk('public')->exists($path)) {
                    $savedBytes += Storage::disk('public')->size($path);
                    Storage::disk('public')->delete($path);
                }
            }

            // Mark as file deleted
            $ad->file_deleted_at = now();
            // We keep the file_path string just for historical reference or set to null, 
            // but setting file_deleted_at is enough to know it's gone.
            $ad->save();
            $count++;
        }

        $savedMB = round($savedBytes / 1048576, 2);
        $this->info("Cleaned files for {$count} ads. Freed up approximately {$savedMB} MB of space.");
    }
}
