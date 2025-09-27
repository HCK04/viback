<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfessionalController extends Controller
{
    /**
     * Get professional profile by user ID (combines user data + professional profile data)
     */
    public function show($id)
    {
        // Get user with role information
        $user = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $id)
            ->select('users.*', 'roles.name as role_name')
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        Log::info("Professional profile request for user: " . json_encode(['id' => $user->id, 'name' => $user->name, 'role' => $user->role_name]));

        // Define professional types
        $professionalTypes = ['medecin', 'kine', 'orthophoniste', 'psychologue'];
        $organizationTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];

        // Skip admin and patient roles
        if (in_array($user->role_name, ['admin', 'patient'])) {
            return response()->json(['message' => 'Profile not accessible'], 403);
        }

        $profile = null;
        $profileData = [];

        // Handle individual professionals
        if (in_array($user->role_name, $professionalTypes)) {
            $tableName = $user->role_name . '_profiles';
            Log::info("Looking for professional profile in table: $tableName for user_id: $id");
            
            $profile = DB::table($tableName)->where('user_id', $id)->first();
            Log::info("Professional profile found: " . ($profile ? 'YES' : 'NO'));
            
            if ($profile) {
                Log::info("Professional profile data: " . json_encode($profile));
                $profileData = $this->formatProfessionalProfile($user, $profile);
            } else {
                Log::warning("No professional profile found in $tableName for user_id $id");
                // Check available profiles
                $allProfiles = DB::table($tableName)->select('user_id')->get();
                Log::info("Available user_ids in $tableName: " . json_encode($allProfiles->pluck('user_id')));
            }
        }
        // Handle organizations
        elseif (in_array($user->role_name, $organizationTypes)) {
            $tableName = $user->role_name . '_profiles';
            Log::info("Looking for organization profile in table: $tableName for user_id: $id");
            
            $profile = DB::table($tableName)->where('user_id', $id)->first();
            Log::info("Organization profile found: " . ($profile ? 'YES' : 'NO'));
            
            if ($profile) {
                Log::info("Organization profile data: " . json_encode($profile));
                $profileData = $this->formatOrganizationProfile($user, $profile);
            } else {
                Log::warning("No organization profile found in $tableName for user_id $id");
                // Check available profiles
                $allProfiles = DB::table($tableName)->select('user_id')->get();
                Log::info("Available user_ids in $tableName: " . json_encode($allProfiles->pluck('user_id')));
            }
        }

        // If no profile found, return basic user data
        if (!$profile) {
            Log::warning("No profile found for user $id with role {$user->role_name}");
            $profileData = $this->formatBasicProfile($user);
        }

        Log::info("Returning professional profile data for user $id");
        return response()->json($profileData);
    }

    private function formatProfessionalProfile($user, $profile)
    {
        Log::info("=== FORMATTING PROFESSIONAL PROFILE FOR USER {$user->id} ===");
        Log::info("Raw profile data: " . json_encode($profile));
        
        // Robust array parser with cleaning, unicode decoding, and backslash splitting
        $parseArray = function ($value) {
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
                if (preg_match('/\\\u[0-9a-fA-F]{4}/', $s)) {
                    $s = preg_replace_callback('/\\\u([0-9a-fA-F]{4})/', function ($m) { $code = hexdec($m[1]); return mb_convert_encoding(pack('n', $code), 'UTF-8', 'UTF-16BE'); }, $s);
                }
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
                if (preg_match_all('/"(?:\\\\.|[^"\\\\])*"/u', $s, $m) && !empty($m[0])) {
                    $items = [];
                    foreach ($m[0] as $q) {
                        $v = @json_decode($q, true);
                        $items[] = $v !== null ? $cleanOne($v) : $cleanOne(trim($q, '"'));
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
        };

        // Parse JSON fields
        $specialties = [];
        if (isset($profile->specialty) && $profile->specialty) {
            $specialties = $parseArray($profile->specialty);
        }

        // Preserve JSON object structure for CV fields (no flattening)
        $diplomes = (function() use ($profile) {
            $raw = $profile->diplomes ?? $profile->diplomas ?? null;
            if ($raw === null || $raw === '') return [];
            if (is_array($raw)) return $raw;
            if (is_string($raw)) {
                $d = json_decode($raw, true);
                if (is_array($d)) return $d;
                // Try unescaped JSON (handles double-encoding and raw newlines)
                $raw2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $raw));
                $d2 = json_decode($raw2, true);
                if (is_array($d2)) return $d2;
            }
            return [];
        })();

        $experiences = (function() use ($profile) {
            $raw = $profile->experiences ?? null;
            if ($raw === null || $raw === '') return [];
            if (is_array($raw)) return $raw;
            if (is_string($raw)) {
                $d = json_decode($raw, true);
                if (is_array($d)) return $d;
                $raw2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $raw));
                $d2 = json_decode($raw2, true);
                if (is_array($d2)) return $d2;
            }
            return [];
        })();

        $moyensPaiement = [];
        if (isset($profile->moyens_paiement)) {
            Log::info("Raw moyens_paiement: " . $profile->moyens_paiement);
            $moyensPaiement = $parseArray($profile->moyens_paiement);
            Log::info("Parsed moyens_paiement: " . json_encode($moyensPaiement));
        } else {
            Log::info("No moyens_paiement found");
        }

        $moyensTransport = [];
        if (isset($profile->moyens_transport)) {
            Log::info("Raw moyens_transport: " . $profile->moyens_transport);
            $moyensTransport = $parseArray($profile->moyens_transport);
            Log::info("Parsed moyens_transport: " . json_encode($moyensTransport));
        } else {
            Log::info("No moyens_transport found");
        }

        // Parse imgs JSON if present
        $imgs = [];
        if (isset($profile->imgs) && $profile->imgs) {
            try {
                $decoded = json_decode($profile->imgs, true);
                if (is_array($decoded)) {
                    $imgs = $decoded;
                } elseif (is_string($decoded) && strlen($decoded) > 2 && $decoded[0] === '[' && substr($decoded, -1) === ']') {
                    $decoded2 = json_decode($decoded, true);
                    $imgs = is_array($decoded2) ? $decoded2 : [];
                }
            } catch (\Exception $e) {
                $imgs = [];
            }
        }
        // Normalize images to '/storage/...'
        $imgs = array_map([$this, 'normalizeMediaPath'], $imgs);

        $joursDisponibles = [];
        if (isset($profile->jours_disponibles)) {
            Log::info("Raw jours_disponibles: " . $profile->jours_disponibles);
            $joursDisponibles = $parseArray($profile->jours_disponibles);
            Log::info("Parsed jours_disponibles: " . json_encode($joursDisponibles));
        } else {
            Log::info("No jours_disponibles found");
        }

        // Fallback profile image for psychologues when missing
        $profileImage = $profile->profile_image ?? null;
        if (($profileImage === null || $profileImage === '') && $user->role_name === 'psychologue' && !empty($imgs)) {
            $profileImage = $imgs[0];
        }
        $profileImageNorm = $this->normalizeMediaPath($profileImage);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'type' => $user->role_name,
            'role' => $user->role_name,
            'role_name' => $user->role_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'specialty' => $specialties,
            'experience_years' => $profile->experience_years ?? null,
            'adresse' => $profile->adresse ?? null,
            'location' => $profile->adresse ?? null,
            'ville' => $profile->ville ?? null,
            'rating' => $profile->rating ?? 0,
            'presentation' => $profile->presentation ?? null,
            'additional_info' => $profile->additional_info ?? null,
            'informations_pratiques' => $profile->informations_pratiques ?? null,
            'profile_image' => $profileImageNorm,
            'horaire_start' => $profile->horaire_start ?? null,
            'horaire_end' => $profile->horaire_end ?? null,
            'imgs' => $imgs,
            'moyens_paiement' => $moyensPaiement,
            'moyens_transport' => $moyensTransport,
            'jours_disponibles' => $joursDisponibles,
            'diplomes' => $diplomes,
            'experiences' => $experiences,
            'numero_carte_professionnelle' => $profile->numero_carte_professionnelle ?? null,
            'carte_professionnelle' => $profile->carte_professionnelle ?? null,
            'contact_urgence' => $profile->contact_urgence ?? null,
            'disponible' => $profile->disponible ?? true,
            'vacation_mode' => $profile->vacation_mode ?? false,
            'absence_start_date' => $profile->absence_start_date ?? null,
            'absence_end_date' => $profile->absence_end_date ?? null,
            'rdv_patients_suivis_uniquement' => $profile->rdv_patients_suivis_uniquement ?? false,
            'is_verified' => $user->is_verified ?? false,
            'created_at' => $user->created_at
        ];
    }

    private function formatOrganizationProfile($user, $profile)
    {
        // Get organization-specific name
        $orgName = $user->name;
        switch ($user->role_name) {
            case 'clinique':
                $orgName = $profile->nom_clinique ?: $user->name;
                break;
            case 'pharmacie':
                $orgName = $profile->nom_pharmacie ?: $user->name;
                break;
            case 'parapharmacie':
                $orgName = $profile->nom_parapharmacie ?: $user->name;
                break;
            case 'labo_analyse':
                $orgName = $profile->nom_labo ?: $user->name;
                break;
            case 'centre_radiologie':
                $orgName = $profile->nom_centre ?: $user->name;
                break;
        }

        // Parse JSON fields
        $services = [];
        if (isset($profile->services) && $profile->services) {
            $decoded = json_decode($profile->services, true);
            if (is_array($decoded)) {
                $services = $decoded;
            } elseif (is_string($decoded)) {
                $services = json_decode($decoded, true) ?: [];
            }
        }

        $galleryImages = [];
        if (isset($profile->gallery) && $profile->gallery) {
            $decoded = json_decode($profile->gallery, true);
            if (is_array($decoded)) {
                $galleryImages = $decoded;
            }
        } elseif (isset($profile->etablissement_image) && $profile->etablissement_image) {
            $galleryImages = [$profile->etablissement_image];
        }
        // Normalize gallery images
        $galleryImages = array_map([$this, 'normalizeMediaPath'], $galleryImages);

        $horaires = [];
        if (isset($profile->horaires) && $profile->horaires) {
            $decoded = json_decode($profile->horaires, true);
            if (is_array($decoded)) {
                $horaires = $decoded;
            }
        }

        // Cleaner for organization arrays
        $parseArrayClean = function ($value) {
            $cleanOne = function ($s) {
                $s = (string) $s; $s = trim($s);
                if (strlen($s) >= 2 && $s[0] === '"' && substr($s, -1) === '"') { $s = substr($s, 1, -1); }
                $s = str_replace(["\\r", "\\n"], ' ', $s);
                $s = str_replace(['\\"'], '"', $s);
                $s = str_replace(['\\\\'], '\\', $s);
                $s = rtrim($s, '\\');
                if (preg_match('/\\\u[0-9a-fA-F]{4}/', $s)) {
                    $s = preg_replace_callback('/\\\u([0-9a-fA-F]{4})/', function ($m) { $code = hexdec($m[1]); return mb_convert_encoding(pack('n', $code), 'UTF-8', 'UTF-16BE'); }, $s);
                }
                return trim($s, " \t\n\r\0\x0B\"[]");
            };
            $flatten = function (array $list) use ($cleanOne) {
                $out = [];
                foreach ($list as $it) {
                    $parts = preg_split('/\\\\\s*/', $it);
                    foreach ($parts as $p) { $p = $cleanOne($p); if ($p !== '') $out[] = $p; }
                }
                return array_values(array_unique($out));
            };
            if ($value === null || $value === '') return [];
            if (is_array($value)) return $flatten(array_values(array_filter(array_map($cleanOne, $value), fn($v) => $v !== '')));
            if (is_string($value)) {
                $s = trim($value);
                $d = json_decode($s, true); if (is_array($d)) return $flatten(array_map($cleanOne, $d));
                if (is_string($d)) { $d2 = json_decode($d, true); if (is_array($d2)) return $flatten(array_map($cleanOne, $d2)); }
                $s2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $s));
                $d3 = json_decode($s2, true); if (is_array($d3)) return $flatten(array_map($cleanOne, $d3));
                if (is_string($d3)) { $d4 = json_decode($d3, true); if (is_array($d4)) return $flatten(array_map($cleanOne, $d4)); }
                if (preg_match_all('/"(?:\\\\.|[^"\\\\])*"/u', $s, $m) && !empty($m[0])) {
                    $items = []; foreach ($m[0] as $q) { $v = @json_decode($q, true); $items[] = $v !== null ? $cleanOne($v) : $cleanOne(trim($q, '"')); }
                    $items = $flatten(array_values(array_filter($items, fn($v) => $v !== ''))); if (!empty($items)) return $items;
                }
                $parts = null; if (strpos($s, "\n") !== false) $parts = preg_split('/\n+/', $s); else if (strpos($s, ';') !== false) $parts = explode(';', $s); else if (strpos($s, ',') !== false) $parts = explode(',', $s); else $parts = [$s];
                return $flatten(array_values(array_filter(array_map($cleanOne, $parts), fn($v) => $v !== '')));
            }
            return [];
        };

        $moyensPaiement = $parseArrayClean($profile->moyens_paiement ?? null);
        $moyensTransport = $parseArrayClean($profile->moyens_transport ?? null);
        $joursDisponibles = $parseArrayClean($profile->jours_disponibles ?? null);

        // Parse imgs JSON if present
        $imgs = [];
        if (isset($profile->imgs) && $profile->imgs) {
            try {
                $decoded = json_decode($profile->imgs, true);
                $imgs = is_array($decoded) ? $decoded : [];
            } catch (\Exception $e) {
                $imgs = [];
            }
        }
        $imgs = array_map([$this, 'normalizeMediaPath'], $imgs);

        return [
            'id' => $user->id,
            'name' => $orgName,
            'type' => $user->role_name,
            'role' => $user->role_name,
            'role_name' => $user->role_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'adresse' => $profile->adresse ?? null,
            'location' => $profile->adresse ?? null,
            'ville' => $profile->ville ?? null,
            'localisation' => $profile->localisation ?? null,
            'rating' => $profile->rating ?? 0,
            'description' => $profile->description ?? null,
            'org_presentation' => $profile->org_presentation ?? null,
            'presentation' => $profile->presentation ?? $profile->org_presentation ?? null,
            'services_description' => $profile->services_description ?? null,
            'additional_info' => $profile->additional_info ?? null,
            'informations_pratiques' => $profile->informations_pratiques ?? null,
            'contact_urgence' => $profile->contact_urgence ?? null,
            'gallery' => $galleryImages,
            'imgs' => $imgs,
            'etablissement_image' => $this->normalizeMediaPath($profile->etablissement_image ?? null),
            'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? null),
            'horaires' => $horaires,
            'horaire_start' => $profile->horaire_start ?? null,
            'horaire_end' => $profile->horaire_end ?? null,
            'services' => $services,
            'moyens_paiement' => $moyensPaiement,
            'moyens_transport' => $moyensTransport,
            'jours_disponibles' => $joursDisponibles,
            'is_verified' => $user->is_verified ?? false,
            'responsable_name' => $profile->responsable_name ?? null,
            'gerant_name' => $profile->gerant_name ?? null,
            'nbr_personnel' => $profile->nbr_personnel ?? null,
            'disponible' => $profile->disponible ?? true,
            'vacation_mode' => $profile->vacation_mode ?? false,
            'absence_start_date' => $profile->absence_start_date ?? null,
            'absence_end_date' => $profile->absence_end_date ?? null,
            'created_at' => $user->created_at,
            // Organization-specific name fields
            'nom_clinique' => $profile->nom_clinique ?? null,
            'nom_pharmacie' => $profile->nom_pharmacie ?? null,
            'nom_parapharmacie' => $profile->nom_parapharmacie ?? null,
            'nom_labo' => $profile->nom_labo ?? null,
            'nom_centre' => $profile->nom_centre ?? null
        ];
    }

    private function formatBasicProfile($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'type' => $user->role_name,
            'role' => $user->role_name,
            'role_name' => $user->role_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_verified' => $user->is_verified ?? false,
            'created_at' => $user->created_at
        ];
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
}
