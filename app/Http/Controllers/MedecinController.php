<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MedecinProfile;
use App\Models\KineProfile;
use App\Models\OrthophonisteProfile;
use App\Models\PsychologueProfile;
use App\Models\User;

class MedecinController extends Controller
{
    /**
     * Get all professionnels de santé (médecins, kinés, orthophonistes, psychologues)
     */
    public function index(Request $request)
    {
        // Only authenticated users can access
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Fetch all profiles with their user
        $medecins = MedecinProfile::with('user')->where('disponible', true)->get();
        $kines = KineProfile::with('user')->where('disponible', true)->get();
        $orthos = OrthophonisteProfile::with('user')->where('disponible', true)->get();
        $psychos = PsychologueProfile::with('user')->where('disponible', true)->get();

        $professionals = [];

        foreach ($medecins as $profile) {
            if (!$profile->user) continue;
            $professionals[] = [
                'id' => $profile->user->id,
                'name' => $profile->user->name,
                'role' => 'Médecin',
                'specialty' => $profile->specialty ?? 'Médecine générale',
                'experience' => $profile->experience_years,
                'profile_image' => $profile->profile_image ?? null,
                'adresse' => $profile->adresse ?? null,
                'disponible' => $profile->disponible,
                'horaires' => $profile->horaires ?? null,
                'start_time' => $profile->start_time ?? null,
                'end_time' => $profile->end_time ?? null,
            ];
        }
        foreach ($kines as $profile) {
            if (!$profile->user) continue;
            $professionals[] = [
                'id' => $profile->user->id,
                'name' => $profile->user->name,
                'role' => 'Kinésithérapeute',
                'specialty' => $profile->specialty ?? 'Kinésithérapie',
                'experience' => $profile->experience_years,
                'profile_image' => $profile->profile_image ?? null,
                'adresse' => $profile->adresse ?? null,
                'disponible' => $profile->disponible,
                'horaires' => $profile->horaires ?? null,
                'start_time' => $profile->start_time ?? null,
                'end_time' => $profile->end_time ?? null,
            ];
        }
        foreach ($orthos as $profile) {
            if (!$profile->user) continue;
            $professionals[] = [
                'id' => $profile->user->id,
                'name' => $profile->user->name,
                'role' => 'Orthophoniste',
                'specialty' => $profile->specialty ?? 'Orthophonie',
                'experience' => $profile->experience_years,
                'profile_image' => $profile->profile_image ?? null,
                'adresse' => $profile->adresse ?? null,
                'disponible' => $profile->disponible,
                'horaires' => $profile->horaires ?? null,
                'start_time' => $profile->start_time ?? null,
                'end_time' => $profile->end_time ?? null,
            ];
        }
        foreach ($psychos as $profile) {
            if (!$profile->user) continue;
            $professionals[] = [
                'id' => $profile->user->id,
                'name' => $profile->user->name,
                'role' => 'Psychologue',
                'specialty' => $profile->specialty ?? 'Psychologie',
                'experience' => $profile->experience_years,
                'profile_image' => $profile->profile_image ?? null,
                'adresse' => $profile->adresse ?? null,
                'disponible' => $profile->disponible,
                'horaires' => $profile->horaires ?? null,
                'start_time' => $profile->start_time ?? null,
                'end_time' => $profile->end_time ?? null,
            ];
        }

        // Sort by name for a clean UI
        usort($professionals, fn($a, $b) => strcmp($a['name'], $b['name']));

        return response()->json($professionals);
    }

    public function show($id)
    {
        // Try to find the user
        $user = \App\Models\User::find($id);
        if (!$user) {
            return response()->json(['error' => 'Utilisateur non trouvé'], 404);
        }

        // Try to find the profile in all professional tables
        $profile =
            \App\Models\MedecinProfile::where('user_id', $id)->first() ??
            \App\Models\KineProfile::where('user_id', $id)->first() ??
            \App\Models\OrthophonisteProfile::where('user_id', $id)->first() ??
            \App\Models\PsychologueProfile::where('user_id', $id)->first();

        if (!$profile) {
            return response()->json(['error' => 'Profil professionnel non trouvé'], 404);
        }

        // Compose response with complete profile data
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role ? $user->role->name : null,
            'specialty' => $profile->specialty ?? $user->role->name,
            'experience' => $profile->experience_years ?? null,
            'profile_image' => $profile->profile_image ?? null,
            'adresse' => $profile->adresse ?? null,
            'location' => $profile->adresse ?? null, // Add location alias
            'disponible' => $profile->disponible ?? null,
            'horaires' => $profile->horaires ?? null,
            'start_time' => $profile->start_time ?? null,
            'end_time' => $profile->end_time ?? null,
        ]);
    }

    public function availableHours($id)
    {
        try {
            // Try to find the profile in all professional tables
            $profile =
                \App\Models\MedecinProfile::where('user_id', $id)->first() ??
                \App\Models\KineProfile::where('user_id', $id)->first() ??
                \App\Models\OrthophonisteProfile::where('user_id', $id)->first() ??
                \App\Models\PsychologueProfile::where('user_id', $id)->first();

            if (!$profile) {
                \Log::info("No profile found for user ID: $id");
                return response()->json([]);
            }

            \Log::info("Profile found for user ID: $id");
            \Log::info("horaires field: " . $profile->horaires);
            \Log::info("horaire_start: " . $profile->horaire_start);
            \Log::info("horaire_end: " . $profile->horaire_end);

            // Parse doctor's working hours from JSON horaires field
            $hours = [];
            
            if ($profile->horaires) {
                // Try to decode JSON horaires field
                $horairesData = json_decode($profile->horaires, true);
                
                if ($horairesData && isset($horairesData['start']) && isset($horairesData['end'])) {
                    $startTime = $horairesData['start'];
                    $endTime = $horairesData['end'];
                    
                    \Log::info("Parsed JSON horaires - start: $startTime, end: $endTime");
                    
                    $startHour = (int) date('H', strtotime($startTime));
                    $startMin = (int) date('i', strtotime($startTime));
                    
                    // Handle 24:00 as end of day
                    if ($endTime === '24:00') {
                        $endHour = 24;
                        $endMin = 0;
                    } else {
                        $endHour = (int) date('H', strtotime($endTime));
                        $endMin = (int) date('i', strtotime($endTime));
                    }
                    
                    \Log::info("Calculated hours - startHour: $startHour, endHour: $endHour");
                    
                    // Handle 24-hour schedule or overnight schedules
                    if ($startHour == 0 && $endHour == 24) {
                        // Full 24-hour schedule: 00:00 to 24:00
                        \Log::info("Generating full 24-hour schedule");
                        for ($hour = 0; $hour < 24; $hour++) {
                            $hours[] = sprintf('%02d:00', $hour);
                        }
                    } else if ($endHour < $startHour || $endHour == 24) {
                        // Generate slots from start time to midnight
                        $currentHour = $startHour;
                        $currentMin = $startMin;
                        
                        while ($currentHour < 24) {
                            $hours[] = sprintf('%02d:%02d', $currentHour, $currentMin);
                            
                            $currentMin += 60;
                            if ($currentMin >= 60) {
                                $currentHour += intdiv($currentMin, 60);
                                $currentMin = $currentMin % 60;
                            }
                        }
                        
                        // If end time is not midnight (24:00), generate slots from midnight to end time
                        if ($endHour < 24) {
                            $currentHour = 0;
                            $currentMin = 0;
                            
                            while ($currentHour < $endHour || ($currentHour == $endHour && $currentMin < $endMin)) {
                                $hours[] = sprintf('%02d:%02d', $currentHour, $currentMin);
                                
                                $currentMin += 60;
                                if ($currentMin >= 60) {
                                    $currentHour += intdiv($currentMin, 60);
                                    $currentMin = $currentMin % 60;
                                }
                            }
                        }
                    } else {
                        // Normal schedule within same day
                        $currentHour = $startHour;
                        $currentMin = $startMin;
                        
                        while ($currentHour < $endHour || ($currentHour == $endHour && $currentMin < $endMin)) {
                            $hours[] = sprintf('%02d:%02d', $currentHour, $currentMin);
                            
                            $currentMin += 60;
                            if ($currentMin >= 60) {
                                $currentHour += intdiv($currentMin, 60);
                                $currentMin = $currentMin % 60;
                            }
                        }
                    }
                }
                // Fallback: try to parse old string format
                else if (preg_match('/^(\d{1,2}):(\d{2})-(\d{1,2}):(\d{2})$/', $profile->horaires, $matches)) {
                    $startHour = intval($matches[1]);
                    $startMin = intval($matches[2]);
                    $endHour = intval($matches[3]);
                    $endMin = intval($matches[4]);
                    
                    $currentHour = $startHour;
                    $currentMin = $startMin;
                    
                    while ($currentHour < $endHour || ($currentHour == $endHour && $currentMin < $endMin)) {
                        $hours[] = sprintf('%02d:%02d', $currentHour, $currentMin);
                        
                        $currentMin += 60;
                        if ($currentMin >= 60) {
                            $currentHour += intdiv($currentMin, 60);
                            $currentMin = $currentMin % 60;
                        }
                    }
                }
            }
            
            // If no valid schedule found, check for horaire_start/horaire_end fields
            if (empty($hours)) {
                \Log::info("No hours generated from horaires, checking horaire_start/horaire_end fields");
                
                if ($profile->horaire_start && $profile->horaire_end) {
                    $startHour = (int) date('H', strtotime($profile->horaire_start));
                    $endHour = (int) date('H', strtotime($profile->horaire_end));
                    
                    \Log::info("Using horaire_start: {$profile->horaire_start}, horaire_end: {$profile->horaire_end}");
                    
                    // Generate hourly slots between start and end time
                    for ($hour = $startHour; $hour < $endHour; $hour++) {
                        $hours[] = sprintf('%02d:00', $hour);
                    }
                }
                
                // Final fallback to default hours
                if (empty($hours)) {
                    \Log::info("No hours generated from any source, using default schedule");
                    $hours = ['08:00', '09:00', '10:00', '11:00', '14:00', '15:00', '16:00', '17:00'];
                }
            }

            // Get the selected date from request (default to today)
            $selectedDate = request('date', date('Y-m-d'));
            
            // Remove already booked hours for the selected date
            $booked = \App\Models\Rdv::where('target_user_id', $id)
                ->whereDate('date_time', $selectedDate)
                ->pluck('date_time')
                ->map(function ($dt) {
                    return date('H:i', strtotime($dt));
                })->toArray();

            // Create response with all hours and their availability status
            $currentTime = date('H:i');
            $isToday = ($selectedDate === date('Y-m-d'));
            
            $response = [];
            foreach ($hours as $hour) {
                $isBooked = in_array($hour, $booked);
                
                // For today, check if slot is at least 30 minutes in the future
                $isPast = false;
                if ($isToday) {
                    $currentHour = (int) date('H');
                    $currentMin = (int) date('i');
                    $slotHour = (int) substr($hour, 0, 2);
                    $slotMin = (int) substr($hour, 3, 2);
                    
                    // Special case: 00:00 (midnight) is always available for next day
                    if ($hour === '00:00') {
                        $isPast = false;
                    } else {
                        // Calculate if slot is less than 30 minutes from now
                        $currentTotalMin = $currentHour * 60 + $currentMin;
                        $slotTotalMin = $slotHour * 60 + $slotMin;
                        $isPast = ($slotTotalMin <= $currentTotalMin + 30);
                    }
                }
                
                $isAvailable = !$isBooked && !$isPast;
                
                $response[] = [
                    'time' => $hour,
                    'available' => $isAvailable,
                    'booked' => $isBooked,
                    'past' => $isPast
                ];
            }

            // Sort by time
            usort($response, function($a, $b) {
                return strcmp($a['time'], $b['time']);
            });

            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json([
                '08:00', '09:00', '10:00', '11:00', 
                '14:00', '15:00', '16:00', '17:00'
            ]);
        }
    }
}
