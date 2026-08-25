<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Advertisement;
use Illuminate\Support\Facades\DB;

class UpdateAdStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ads:update-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update advertisement statuses based on their end dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = now()->toDateString();

        // Expire ads that have passed their end date
        $expiredCount = Advertisement::where('status', 'Active')
            ->where('is_deleted', 0)
            ->where('end_date', '<', $today)
            ->update(['status' => 'Expired']);

        if ($expiredCount > 0) {
            $this->info("Updated {$expiredCount} ads to Expired.");
        } else {
            $this->info("No ads to expire today.");
        }
    }
}
