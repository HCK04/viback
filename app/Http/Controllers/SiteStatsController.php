<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SiteStatsController extends Controller
{
    // GET /api/site-stats: returns baseline and start_time
    public function getStats(Request $request)
    {
        $row = DB::table('site_stats')->first();

        if (!$row) {
            $now = Carbon::now();
            DB::table('site_stats')->insert([
                'baseline' => 12000,
                'start_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $row = DB::table('site_stats')->first();
        }

        return response()->json([
            'baseline' => (int) $row->baseline,
            'start_time' => Carbon::parse($row->start_time)->toIso8601String(),
        ]);
    }

    // POST /api/site-stats/bump: set new baseline and reset start_time to now
    public function bump(Request $request)
    {
        $data = $request->validate([
            'baseline' => ['required', 'integer', 'min:0'],
        ]);

        $now = Carbon::now();
        $exists = DB::table('site_stats')->exists();

        if ($exists) {
            DB::table('site_stats')->limit(1)->update([
                'baseline' => $data['baseline'],
                'start_time' => $now,
                'updated_at' => $now,
            ]);
        } else {
            DB::table('site_stats')->insert([
                'baseline' => $data['baseline'],
                'start_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return response()->json([
            'baseline' => $data['baseline'],
            'start_time' => $now->toIso8601String(),
        ]);
    }
}
