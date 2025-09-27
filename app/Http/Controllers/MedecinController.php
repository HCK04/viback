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
     * Get public individual professional profile by ID
     */
    public function publicShow($id)
    {
        // Try different professional profile types
        $profileTypes = [
            'medecin_profiles' => MedecinProfile::class,
            'kine_profiles' => KineProfile::class,
            'orthophoniste_profiles' => OrthophonisteProfile::class,
            'psychologue_profiles' => PsychologueProfile::class,
        ];

        foreach ($profileTypes as $table => $model) {
            $profile = $model::with('user')->where('user_id', $id)->first();
            if ($profile && $profile->user) {
                // Normalize imgs to array of '/storage/...' paths if possible
                $normalizeImgs = function($raw) {
                    if (!$raw) return null;
                    try {
                        $parsed = json_decode($raw, true);
                        if (is_array($parsed)) {
                            return array_map([$this, 'normalizeMediaPath'], $parsed);
                        }
                    } catch (\Throwable $e) {}
                    // Keep string but normalize path
                    return $this->normalizeMediaPath($raw);
                };

                return response()->json([
                    'id' => $profile->user->id,
                    'name' => $profile->user->name,
                    'email' => $profile->user->email,
                    'phone' => $profile->user->phone,
                    'type' => str_replace('_profiles', '', $table),
                    'specialty' => $profile->specialty ?? null,
                    'experience_years' => $profile->experience_years ?? null,
                    'adresse' => $profile->adresse ?? null,
                    'ville' => $profile->ville ?? null,
                    'horaire_start' => $profile->horaire_start ?? null,
                    'horaire_end' => $profile->horaire_end ?? null,
                    'disponible' => $profile->disponible ?? true,
                    'rating' => $profile->rating ?? 0,
                    'presentation' => $profile->presentation ?? null,
                    'additional_info' => $profile->additional_info ?? null,
                    'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? null),
                    'etablissement_image' => $this->normalizeMediaPath($profile->etablissement_image ?? null),
                    // Expose gallery images; keep as array if JSON, otherwise raw string
                    'imgs' => $normalizeImgs($profile->imgs ?? null),
                    'gallery' => $normalizeImgs($profile->gallery ?? null),
                    'carte_professionnelle' => $this->normalizeMediaPath($profile->carte_professionnelle ?? null),
                    // Cleaned practical fields
                    'moyens_paiement' => $this->parseArrayLoose($profile->moyens_paiement ?? null),
                    'moyens_transport' => $this->parseArrayLoose($profile->moyens_transport ?? null),
                    'jours_disponibles' => $this->parseArrayLoose($profile->jours_disponibles ?? null),
                    // CV fields (exposed on public endpoint as requested)
                    'diplomes' => (function() use ($profile) {
                        $raw = $profile->diplomes ?? ($profile->diplomas ?? null);
                        return $raw; // keep raw (string JSON or array); frontend handles parsing
                    })(),
                    'experiences' => $profile->experiences ?? null,
                    'is_verified' => $profile->user->is_verified,
                    'created_at' => $profile->user->created_at
                ]);
            }
        }

        return response()->json(['message' => 'Professional not found'], 404);
    }

    /**
     * Public index of professionals (medecins, kinés, orthophonistes, psychologues)
     * Returns minimal fields with normalized media paths. No auth required.
     */
    public function publicIndex(Request $request)
    {
        $lists = [
            ['type' => 'medecin', 'model' => MedecinProfile::class],
            ['type' => 'kine', 'model' => KineProfile::class],
            ['type' => 'orthophoniste', 'model' => OrthophonisteProfile::class],
            ['type' => 'psychologue', 'model' => PsychologueProfile::class],
        ];

        $out = [];
        foreach ($lists as $cfg) {
            $rows = $cfg['model']::with('user')->get();
            foreach ($rows as $row) {
                if (!$row->user) continue;
                $imgs = [];
                // Try to decode imgs/gallery as arrays and normalize
                foreach (['imgs', 'gallery'] as $col) {
                    try {
                        $v = $row->$col ?? null;
                        if ($v) {
                            $arr = is_string($v) ? json_decode($v, true) : (is_array($v) ? $v : []);
                            if (is_array($arr)) {
                                foreach ($arr as $item) {
                                    $n = $this->normalizeMediaPath($item);
                                    if ($n) $imgs[] = $n;
                                }
                            }
                        }
                    } catch (\Throwable $e) { /* ignore */ }
                }
                // Add common single-image fields if no gallery
                $fallbacks = [
                    $this->normalizeMediaPath($row->profile_image ?? null),
                    $this->normalizeMediaPath($row->etablissement_image ?? null),
                    $this->normalizeMediaPath($row->carte_professionnelle ?? null),
                ];
                foreach ($fallbacks as $f) { if ($f) $imgs[] = $f; }
                $imgs = array_values(array_unique(array_filter($imgs)));

                $out[] = [
                    'id' => $row->user->id,
                    'name' => $row->user->name,
                    'type' => $cfg['type'],
                    'role' => $cfg['type'],
                    'role_name' => $cfg['type'],
                    'email' => $row->user->email,
                    'phone' => $row->user->phone,
                    'profile_image' => $this->normalizeMediaPath($row->profile_image ?? null),
                    'etablissement_image' => $this->normalizeMediaPath($row->etablissement_image ?? null),
                    'imgs' => $imgs,
                    'rating' => $row->rating ?? 0,
                    'ville' => $row->ville ?? null,
                    'adresse' => $row->adresse ?? null,
                    'disponible' => $row->disponible ?? true,
                ];
            }
        }

        // Sort by name for deterministic order
        usort($out, fn($a, $b) => strcmp($a['name'], $b['name']));
        return response()->json($out);
    }

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
                'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? null),
                'adresse' => $profile->adresse ?? null,
                'disponible' => $profile->disponible,
                'horaires' => $profile->horaires ?? null,
                'horaire_start' => $profile->horaire_start ?? null,
                'horaire_end' => $profile->horaire_end ?? null,
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
                'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? null),
                'adresse' => $profile->adresse ?? null,
                'disponible' => $profile->disponible,
                'horaires' => $profile->horaires ?? null,
                'horaire_start' => $profile->horaire_start ?? null,
                'horaire_end' => $profile->horaire_end ?? null,
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
                'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? null),
                'adresse' => $profile->adresse ?? null,
                'disponible' => $profile->disponible,
                'horaires' => $profile->horaires ?? null,
                'horaire_start' => $profile->horaire_start ?? null,
                'horaire_end' => $profile->horaire_end ?? null,
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
                'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? null),
                'adresse' => $profile->adresse ?? null,
                'disponible' => $profile->disponible,
                'horaires' => $profile->horaires ?? null,
                'horaire_start' => $profile->horaire_start ?? null,
                'horaire_end' => $profile->horaire_end ?? null,
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

        // Compose response with complete profile data including CV fields
        // Normalize imgs similarly to publicShow
        $normalizeImgs = function($raw) {
            if (!$raw) return null;
            try {
                $parsed = json_decode($raw, true);
                if (is_array($parsed)) {
                    return array_map([$this, 'normalizeMediaPath'], $parsed);
                }
            } catch (\Throwable $e) {}
            return $this->normalizeMediaPath($raw);
        };

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role ? $user->role->name : null,
            'specialty' => $profile->specialty ?? $user->role->name,
            'experience' => $profile->experience_years ?? null,
            'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? null),
            'etablissement_image' => $this->normalizeMediaPath($profile->etablissement_image ?? null),
            'adresse' => $profile->adresse ?? null,
            'location' => $profile->adresse ?? null, // Add location alias
            'disponible' => $profile->disponible ?? null,
            'horaires' => $profile->horaires ?? null,
            'horaire_start' => $profile->horaire_start ?? null,
            'horaire_end' => $profile->horaire_end ?? null,
            // Expose gallery images for public profile
            'imgs' => $normalizeImgs($profile->imgs ?? null),
            'gallery' => $normalizeImgs($profile->gallery ?? null),
            // CV fields
            'presentation' => $profile->presentation ?? null,
            'carte_professionnelle' => $this->normalizeMediaPath($profile->carte_professionnelle ?? null),
            'diplomes' => ($profile->diplomes ?? ($profile->diplomas ?? null)),
            'experiences' => $profile->experiences ?? null,
            'additional_info' => $profile->additional_info ?? null,
            // New profile fields (cleaned arrays)
            'moyens_paiement' => $this->parseArrayLoose($profile->moyens_paiement ?? null),
            'moyens_transport' => $this->parseArrayLoose($profile->moyens_transport ?? null),
            'informations_pratiques' => $profile->informations_pratiques ?? null,
            'jours_disponibles' => $this->parseArrayLoose($profile->jours_disponibles ?? null),
            'contact_urgence' => $profile->contact_urgence ?? null,
            'rdv_patients_suivis_uniquement' => $profile->rdv_patients_suivis_uniquement ?? false,
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
                
                // NO FALLBACK - if no hours configured, return empty array
                if (empty($hours)) {
                    \Log::info("No hours configured by professional - returning empty schedule");
                    $hours = [];
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

    /**
     * Normalize a stored media path to a web-accessible '/storage/...' URL path.
     */
    private function normalizeMediaPath($path)
    {
        if ($path === null || $path === '') {
            return $path;
        }
        $p = str_replace('\\', '/', (string) $path);
        $p = ltrim($p);
        // Absolute URL stays as-is
        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }
        // Strip known prefixes
        $p = preg_replace('#^/storage/public/#i', '', $p);
        $p = preg_replace('#^storage/public/#i', '', $p);
        $p = preg_replace('#^/public/#i', '', $p);
        $p = preg_replace('#^public/#i', '', $p);
        $p = preg_replace('#^/storage/#i', '', $p);
        $p = preg_replace('#^storage/#i', '', $p);
        $p2 = ltrim($p, '/');
        // Known public disk directories -> serve under /storage
        if (preg_match('#^(imgs|images|uploads|upload|profiles|etablissements|clinic|clinique|clinics|parapharmacie|parapharmacies|parapharmacie_profiles|pharmacie|pharmacies|pharmacy|pharmacie_profiles|labo|labo_analyse|laboratoire|radiologie|centre_radiologie|etablissement_images|gallery)/#i', $p2)) {
            return '/storage/' . $p2;
        }
        // Heuristic: image files default to /storage
        if (preg_match('#\.(png|jpe?g|webp|gif|bmp|svg)$#i', $p2)) {
            return '/storage/' . $p2;
        }
        // Fallback: ensure leading slash
        return '/' . $p2;
    }

    /**
     * Tolerant parser to clean arrays possibly stored as JSON strings, double-encoded JSON,
     * or malformed strings split by backslashes/newlines. Returns a deduped array of clean strings.
     */
    private function parseArrayLoose($value)
    {
        $cleanOne = function ($s) {
            $s = (string) $s;
            $s = trim($s);
            if (strlen($s) >= 2 && $s[0] === '"' && substr($s, -1) === '"') {
                $s = substr($s, 1, -1);
            }
            $s = str_replace(["\\r", "\\n"], ' ', $s);
            $s = str_replace(['\\"'], '"', $s);
            $s = str_replace(['\\\\'], '\\', $s);
            $s = rtrim($s, '\\');
            return trim($s, " \t\n\r\0\x0B\"[]");
        };
        $flatten = function (array $list) use ($cleanOne) {
            $out = [];
            foreach ($list as $it) {
                $parts = preg_split('/\\\\\s*/', $it);
                foreach ($parts as $p) {
                    $p = $cleanOne($p);
                    if ($p !== '') $out[] = $p;
                }
            }
            return array_values(array_unique($out));
        };
        if ($value === null) return [];
        if (is_array($value)) {
            $arr = [];
            foreach ($value as $v) if (is_string($v)) $arr[] = $cleanOne($v);
            return $flatten(array_values(array_filter($arr, fn($v) => $v !== '')));
        }
        if (is_string($value)) {
            $s = trim($value);
            if ($s === '') return [];
            $decoded = json_decode($s, true);
            if (is_array($decoded)) return $flatten(array_map($cleanOne, $decoded));
            if (is_string($decoded)) {
                $d2 = json_decode($decoded, true);
                if (is_array($d2)) return $flatten(array_map($cleanOne, $d2));
            }
            $s2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $s));
            $decoded2 = json_decode($s2, true);
            if (is_array($decoded2)) return $flatten(array_map($cleanOne, $decoded2));
            if (is_string($decoded2)) {
                $d3 = json_decode($decoded2, true);
                if (is_array($d3)) return $flatten(array_map($cleanOne, $d3));
            }
            if (preg_match_all('/"(?:\\\\.|[^"\\])*"/', $s, $m) && !empty($m[0])) {
                $items = [];
                foreach ($m[0] as $q) {
                    $qv = json_decode($q, true);
                    $items[] = is_string($qv) ? $cleanOne($qv) : $cleanOne($q);
                }
                $items = $flatten(array_values(array_filter($items, fn($v) => $v !== '')));
                if (!empty($items)) return $items;
            }
            $parts = null;
            if (strpos($s, "\n") !== false) $parts = preg_split('/\n+/', $s); else if (strpos($s, ';') !== false) $parts = explode(';', $s); else if (strpos($s, ',') !== false) $parts = explode(',', $s); else $parts = [$s];
            $parts = array_map($cleanOne, $parts);
            return $flatten(array_values(array_filter($parts, fn($v) => $v !== '')));
        }
        return [];
    }
}
