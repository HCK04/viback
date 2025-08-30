<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatisticsController extends Controller
{
    /**
     * Get current statistics with auto-increment
     */
    public function getStatistics()
    {
        try {
            // Initialize statistics if they don't exist
            $this->initializeStatistics();
            
            $stats = [];
            
            // Get patients count (static)
            $patientsCount = DB::table('users')
                ->join('roles', 'users.role_id', '=', 'roles.id')
                ->where('roles.name', 'patient')
                ->count();
            
            $stats['patients'] = $patientsCount;
            
            // Get doctors with auto-increment (every 10 minutes)
            $doctorsStats = DB::table('statistics')->where('type', 'doctors')->first();
            if ($doctorsStats) {
                $minutesPassed = Carbon::parse($doctorsStats->start_time)->diffInMinutes(Carbon::now());
                $increments = floor($minutesPassed / 10); // Every 10 minutes
                $stats['doctors'] = $doctorsStats->baseline + $increments;
            } else {
                $stats['doctors'] = 350; // Default fallback
            }
            
            // Get pharmacies with auto-increment (every hour)
            $pharmaciesStats = DB::table('statistics')->where('type', 'pharmacies')->first();
            if ($pharmaciesStats) {
                $hoursPassed = Carbon::parse($pharmaciesStats->start_time)->diffInHours(Carbon::now());
                $stats['pharmacies'] = $pharmaciesStats->baseline + $hoursPassed;
            } else {
                $stats['pharmacies'] = 90; // Default fallback
            }
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching statistics: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Initialize statistics table with baseline values
     */
    private function initializeStatistics()
    {
        $now = Carbon::now();
        
        // Initialize doctors statistics
        $doctorsExists = DB::table('statistics')->where('type', 'doctors')->exists();
        if (!$doctorsExists) {
            DB::table('statistics')->insert([
                'type' => 'doctors',
                'baseline' => 350,
                'start_time' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
        
        // Initialize pharmacies statistics
        $pharmaciesExists = DB::table('statistics')->where('type', 'pharmacies')->exists();
        if (!$pharmaciesExists) {
            DB::table('statistics')->insert([
                'type' => 'pharmacies',
                'baseline' => 90,
                'start_time' => $now,
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
    }
    
    /**
     * Reset statistics (admin function)
     */
    public function resetStatistics(Request $request)
    {
        try {
            $type = $request->input('type');
            $baseline = $request->input('baseline', 0);
            
            if (!in_array($type, ['doctors', 'pharmacies'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid type. Must be doctors or pharmacies.'
                ], 400);
            }
            
            $now = Carbon::now();
            
            DB::table('statistics')
                ->where('type', $type)
                ->update([
                    'baseline' => $baseline,
                    'start_time' => $now,
                    'updated_at' => $now
                ]);
            
            return response()->json([
                'success' => true,
                'message' => "Statistics for {$type} reset successfully."
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error resetting statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}
