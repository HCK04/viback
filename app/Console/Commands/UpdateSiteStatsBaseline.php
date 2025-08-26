<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class UpdateSiteStatsBaseline extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'site-stats:update-baseline';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update site_stats baseline every 100,000 seconds (+10,000 patients)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $row = DB::table('site_stats')->first();
        if (!$row) {
            $this->info('No site_stats row found.');
            return;
        }

        $start = Carbon::parse($row->start_time);
        $now = Carbon::now();
        $secondsPassed = $now->diffInSeconds($start);

        if ($secondsPassed >= 100000) {
            $newBaseline = $row->baseline + 10000;
            DB::table('site_stats')->limit(1)->update([
                'baseline' => $newBaseline,
                'start_time' => $now,
                'updated_at' => $now,
            ]);
            $this->info("Baseline updated to $newBaseline at $now");
        } else {
            $this->info("No update needed. Seconds passed: $secondsPassed");
        }

        return Command::SUCCESS;
    }
}
