<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;

class ProfileController extends Controller
{
    /**
     * Get any user profile by ID - auto-detects type and returns appropriate data
     */
    public function show($id)
    {
        Log::info("ProfileController::show called with ID: $id");
        try {
            // Detect roles table availability; fall back gracefully if missing
            $hasRolesTable = Schema::hasTable('roles') && Schema::hasColumn('roles', 'id') && Schema::hasColumn('roles', 'name');
            $hasUsersRoleId = Schema::hasTable('users') && Schema::hasColumn('users', 'role_id');

            $query = DB::table('users');
            if ($hasRolesTable && $hasUsersRoleId) {
                $query = $query->leftJoin('roles', 'users.role_id', '=', 'roles.id')
                    ->select('users.*', 'roles.name as role_name');
            } else {
                // No roles table: still select users.* and synthesize role_name later
                $query = $query->select('users.*', DB::raw('NULL as role_name'));
            }

            $user = $query->where('users.id', $id)->first();

            if (!$user) {
                Log::error("User not found for ID: $id");
                return response()->json(['message' => 'Profile not found'], 404);
            }

            // If role_name is unknown, try to infer by checking existing profile tables for this user_id
            if (empty($user->role_name)) {
                $candidateTables = [
                    'medecin' => 'medecin_profiles',
                    'kine' => 'kine_profiles',
                    'orthophoniste' => 'orthophoniste_profiles',
                    'psychologue' => 'psychologue_profiles',
                    'clinique' => 'clinique_profiles',
                    'pharmacie' => 'pharmacie_profiles',
                    'parapharmacie' => 'parapharmacie_profiles',
                    'labo_analyse' => 'labo_analyse_profiles',
                    'centre_radiologie' => 'centre_radiologie_profiles',
                ];
                foreach ($candidateTables as $role => $tbl) {
                    if (Schema::hasTable($tbl)) {
                        $exists = DB::table($tbl)->where('user_id', $id)->exists();
                        if ($exists) { $user->role_name = $role; break; }
                    }
                }
            }

            Log::info("User found: " . json_encode(['id' => $user->id, 'name' => $user->name, 'role' => $user->role_name]));

            // Define profile types
            $doctorTypes = ['medecin'];
            $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
            $professionalTypes = ['kine', 'orthophoniste', 'psychologue'];

            // Skip admin and patient roles
            if (!empty($user->role_name) && in_array($user->role_name, ['admin', 'patient'])) {
                return response()->json(['message' => 'Profile not accessible'], 403);
            }

            $profile = null;
            $profileData = [];

            // Handle doctors
            if (!empty($user->role_name) && in_array($user->role_name, $doctorTypes)) {
                if (Schema::hasTable('medecin_profiles')) {
                    $has = function ($col) { return Schema::hasColumn('medecin_profiles', $col); };
                    $profile = DB::table('medecin_profiles')
                        ->where('user_id', $id)
                        ->select(
                            '*',
                            $has('diplomas') ? 'diplomas' : DB::raw('NULL as diplomas')
                        )
                        ->first();
                }
                if ($profile) {
                    $profileData = $this->formatDoctorProfile($user, $profile);
                }
            }
            // Handle organizations
            elseif (!empty($user->role_name) && in_array($user->role_name, $orgTypes)) {
                $tableName = $user->role_name . '_profiles';
                Log::info("Looking for profile in table: $tableName for user_id: $id");
                if (Schema::hasTable($tableName)) {
                    $profile = DB::table($tableName)->where('user_id', $id)->first();
                } else {
                    Log::warning("Profile table $tableName does not exist");
                }
                Log::info("Profile found: " . ($profile ? 'YES' : 'NO'));
                if ($profile) {
                    Log::info("Profile data: " . json_encode($profile));
                    $profileData = $this->formatOrganizationProfile($user, $profile);
                } else {
                    Log::warning("No profile found in $tableName for user_id $id");
                }
            }
            // Handle other professionals
            elseif (!empty($user->role_name) && in_array($user->role_name, $professionalTypes)) {
                $tableName = $user->role_name . '_profiles';
                if (Schema::hasTable($tableName)) {
                    $has = function ($col) use ($tableName) { return Schema::hasColumn($tableName, $col); };
                    $profile = DB::table($tableName)
                        ->where('user_id', $id)
                        ->select(
                            '*',
                            $has('diplomas') ? 'diplomas' : DB::raw('NULL as diplomas')
                        )
                        ->first();
                }
                if ($profile) {
                    $profileData = $this->formatProfessionalProfile($user, $profile);
                }
            }

            // If no specific role or no profile found, attempt best-effort detection across known tables
            if (!$profile) {
                Log::warning("No profile found for user $id with role " . ($user->role_name ?? 'unknown'));
                // Try to find any matching row in known profile tables
                $allTables = [
                    'medecin_profiles' => 'doctor',
                    'kine_profiles' => 'kine',
                    'orthophoniste_profiles' => 'orthophoniste',
                    'psychologue_profiles' => 'psychologue',
                    'clinique_profiles' => 'clinique',
                    'pharmacie_profiles' => 'pharmacie',
                    'parapharmacie_profiles' => 'parapharmacie',
                    'labo_analyse_profiles' => 'labo_analyse',
                    'centre_radiologie_profiles' => 'centre_radiologie',
                ];
                foreach ($allTables as $tbl => $kind) {
                    if (!Schema::hasTable($tbl)) continue;
                    $has = function ($col) use ($tbl) { return Schema::hasColumn($tbl, $col); };
                    $row = DB::table($tbl)
                        ->where('user_id', $id)
                        ->select(
                            '*',
                            $has('diplomas') ? 'diplomas' : DB::raw('NULL as diplomas')
                        )
                        ->first();
                    if ($row) {
                        // Use appropriate formatter if possible
                        if (in_array($kind, $doctorTypes)) {
                            $profileData = $this->formatDoctorProfile($user, $row);
                        } elseif (in_array($kind, $orgTypes)) {
                            $profileData = $this->formatOrganizationProfile($user, $row);
                        } else {
                            $profileData = $this->formatProfessionalProfile($user, $row);
                        }
                        $profile = $row;
                        break;
                    }
                }
            }

            // If still no profile found, return basic user data (prevents 500 and lets frontend display minimal info)
            if (!$profile) {
                $profileData = $this->formatBasicProfile($user);
            }

            Log::info("Returning profile data for user $id");
            return response()->json($profileData);
        } catch (QueryException $e) {
            Log::error('DB error in ProfileController::show', [
                'id' => $id,
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'code' => $e->getCode(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error fetching profile'], 500);
        } catch (\Exception $e) {
            Log::error('Error in ProfileController::show', [
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error fetching profile'], 500);
        }
    }

    private function formatDoctorProfile($user, $profile)
    {
        // Robust array parser: handles malformed JSON, unicode escapes, stray brackets, backslashes, and newlines
        $parseArray = function ($value) {
            $cleanOne = function ($s) {
                $s = (string) $s;
                $s = trim($s);
                // Remove wrapping quotes
                if (strlen($s) >= 2 && ((($s[0] === '"') && substr($s, -1) === '"') || (($s[0] === "'") && substr($s, -1) === "'"))) {
                    $s = substr($s, 1, -1);
                }
                // Unescape common sequences and remove stray trailing backslashes
                $s = str_replace(['\\r', '\\n'], ' ', $s);
                $s = str_replace(['\\"'], '"', $s);
                $s = str_replace(['\\\\'], '\\', $s);
                $s = str_replace(['\\/'], '/', $s);
                $s = rtrim($s, '\\');
                // Trim leftover brackets and quotes
                $s = trim($s, " \t\n\r\0\x0B\"'[]");
                // Decode unicode escapes (e.g., \\u00e9 -> é)
                if (preg_match('/\\\\u[0-9a-fA-F]{4}/', $s)) {
                    $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
                        $code = hexdec($m[1]);
                        return mb_convert_encoding(pack('n', $code), 'UTF-8', 'UTF-16BE');
                    }, $s);
                }
                return trim($s);
            };
            $flatten = function (array $list) use ($cleanOne) {
                $out = [];
                foreach ($list as $it) {
                    // Split on backslash continuations and literal newlines
                    $parts = preg_split('/\\\\\s*|\r\n|\r|\n/', (string) $it);
                    foreach ($parts as $p) {
                        $p = $cleanOne($p);
                        if ($p !== '') $out[] = $p;
                    }
                }
                // Dedupe while preserving order
                return array_values(array_unique($out));
            };

            if ($value === null) return [];

            if (is_array($value)) {
                // Attempt to reconstruct JSON if array looks like split JSON parts
                $joined = trim(implode('', $value));
                $decoded = json_decode($joined, true);
                if (is_array($decoded)) return $flatten($decoded);
                $arr = [];
                foreach ($value as $item) {
                    if (is_string($item)) $arr[] = $item;
                    elseif (is_array($item)) {
                        foreach ($item as $sub) if (is_string($sub)) $arr[] = $sub;
                    }
                }
                return $flatten($arr);
            }

            if (is_string($value)) {
                $s = trim($value);
                if ($s === '') return [];
                // Try JSON directly
                $decoded = json_decode($s, true);
                if (is_array($decoded)) return $flatten($decoded);
                elseif (is_string($decoded)) {
                    $d2 = json_decode($decoded, true);
                    if (is_array($d2)) return $flatten($d2);
                }
                // Try unescaped JSON (handles double-escaping and raw newlines)
                $s2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $s));
                $decoded2 = json_decode($s2, true);
                if (is_array($decoded2)) return $flatten($decoded2);
                elseif (is_string($decoded2)) {
                    $d3 = json_decode($decoded2, true);
                    if (is_array($d3)) return $flatten($d3);
                }
                // Extract quoted tokens as a last JSON-like attempt
                if (preg_match_all('/"(?:\\\\.|[^"\\\\])*"/', $s, $m) && !empty($m[0])) {
                    $items = [];
                    foreach ($m[0] as $q) {
                        $qv = json_decode($q, true);
                        $items[] = is_string($qv) ? $qv : $q;
                    }
                    $items = array_values(array_filter($items, fn($v) => trim($v) !== ''));
                    if (!empty($items)) return $flatten($items);
                }
                // Fallback split by newlines/semicolons/commas
                if (strpos($s, "\n") !== false) $parts = preg_split('/\n+/', $s);
                elseif (strpos($s, ';') !== false) $parts = explode(';', $s);
                elseif (strpos($s, ',') !== false) $parts = explode(',', $s);
                else $parts = [$s];
                return $flatten($parts);
            }
            return [];
        };

        // Parse specialties
        $specialties = [];
        if ($profile->specialty) {
            $specialties = $parseArray($profile->specialty);
        }

        // Parse imgs: accept JSON array or comma-separated string; always return array of normalized paths
        $imgs = $this->normalizeMediaList($profile->imgs ?? null);

        // Build gallery: accept JSON array or CSV; include establishment/carte images as fallbacks
        $gallery = $this->normalizeMediaList($profile->gallery ?? null);
        if (empty($gallery) && !empty($profile->etablissement_image)) {
            $first = $this->normalizeMediaPathFirst($profile->etablissement_image);
            if ($first) $gallery[] = $first;
        }
        if (!empty($profile->carte_professionnelle)) {
            $cp = $this->normalizeMediaPathFirst($profile->carte_professionnelle);
            if ($cp) $gallery[] = $cp;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'type' => 'medecin',
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
            'profile_image' => $this->normalizeMediaPathFirst($profile->profile_image ?? null),
            'etablissement_image' => $this->normalizeMediaPathFirst($profile->etablissement_image ?? null),
            'carte_professionnelle' => $this->normalizeMediaPathFirst($profile->carte_professionnelle ?? null),
            'horaire_start' => $profile->horaire_start ?? null,
            'horaire_end' => $profile->horaire_end ?? null,
            'imgs' => $imgs,
            'gallery' => $gallery,
            'moyens_paiement' => isset($profile->moyens_paiement) ? $parseArray($profile->moyens_paiement) : [],
            'moyens_transport' => isset($profile->moyens_transport) ? $parseArray($profile->moyens_transport) : [],
            'jours_disponibles' => isset($profile->jours_disponibles) ? $parseArray($profile->jours_disponibles) : [],
            // Diplomas (preserve JSON object structure; fallback to legacy 'diplomas')
            'diplomes' => (function() use ($profile) {
                $raw = $profile->diplomes ?? $profile->diplomas ?? null;
                if (!$raw) return [];
                if (is_array($raw)) return $raw;
                if (is_string($raw)) {
                    $d = json_decode($raw, true);
                    if (is_array($d)) return $d;
                    // Try unescaped JSON
                    $raw2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $raw));
                    $d2 = json_decode($raw2, true);
                    if (is_array($d2)) return $d2;
                }
                return [];
            })(),
            // Experiences (preserve JSON object structure)
            'experiences' => (function() use ($profile) {
                $raw = $profile->experiences ?? null;
                if (!$raw) return [];
                if (is_array($raw)) return $raw;
                if (is_string($raw)) {
                    $d = json_decode($raw, true);
                    if (is_array($d)) return $d;
                    $raw2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $raw));
                    $d2 = json_decode($raw2, true);
                    if (is_array($d2)) return $d2;
                }
                return [];
            })(),
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

        // Gallery for organizations: accept JSON array or CSV; include main image if missing
        $galleryImages = $this->normalizeMediaList($profile->gallery ?? null);
        if (empty($galleryImages) && !empty($profile->etablissement_image)) {
            $first = $this->normalizeMediaPathFirst($profile->etablissement_image);
            if ($first) $galleryImages[] = $first;
        }

        // Parse services
        $services = [];
        if (isset($profile->services) && $profile->services) {
            $decoded = json_decode($profile->services, true);
            if (is_array($decoded)) {
                $services = $decoded;
            } elseif (is_string($decoded)) {
                // Handle double-encoded JSON
                $services = json_decode($decoded, true) ?: [];
            }
        }

        // Parse horaires if available
        $horaires = [];
        if (isset($profile->horaires) && $profile->horaires) {
            $decoded = json_decode($profile->horaires, true);
            if (is_array($decoded)) {
                $horaires = $decoded;
            }
        }

        // Get horaire times
        $horaireStart = $profile->horaire_start ?? null;
        $horaireEnd = $profile->horaire_end ?? null;

        // Hardened array parser (handles malformed JSON, unicode escapes, stray brackets, backslashes, and newlines)
        $parseArray = function ($value) {
            $cleanOne = function ($s) {
                $s = (string) $s;
                $s = trim($s);
                if (strlen($s) >= 2 && ((($s[0] === '"') && substr($s, -1) === '"') || (($s[0] === "'") && substr($s, -1) === "'"))) {
                    $s = substr($s, 1, -1);
                }
                $s = str_replace(['\\r', '\\n'], ' ', $s);
                $s = str_replace(['\\"'], '"', $s);
                $s = str_replace(['\\\\'], '\\', $s);
                $s = rtrim($s, '\\');
                $s = trim($s, " \t\n\r\0\x0B\"'[]");
                if (preg_match('/\\\\u[0-9a-fA-F]{4}/', $s)) {
                    $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
                        $code = hexdec($m[1]);
                        return mb_convert_encoding(pack('n', $code), 'UTF-8', 'UTF-16BE');
                    }, $s);
                }
                return trim($s);
            };
            $flatten = function (array $list) use ($cleanOne) {
                $out = [];
                foreach ($list as $it) {
                    $parts = preg_split('/\\\\\s*|\r\n|\r|\n/', (string) $it);
                    foreach ($parts as $p) {
                        $p = $cleanOne($p);
                        if ($p !== '') $out[] = $p;
                    }
                }
                return array_values(array_unique($out));
            };
            if ($value === null) return [];
            if (is_array($value)) {
                $joined = trim(implode('', $value));
                $decoded = json_decode($joined, true);
                if (is_array($decoded)) return $flatten($decoded);
                $arr = [];
                foreach ($value as $item) {
                    if (is_string($item)) $arr[] = $item;
                    elseif (is_array($item)) foreach ($item as $sub) if (is_string($sub)) $arr[] = $sub;
                }
                return $flatten($arr);
            }
            if (is_string($value)) {
                $s = trim($value);
                if ($s === '') return [];
                $decoded = json_decode($s, true);
                if (is_array($decoded)) return $flatten($decoded);
                elseif (is_string($decoded)) {
                    $d2 = json_decode($decoded, true);
                    if (is_array($d2)) return $flatten($d2);
                }
                $s2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $s));
                $decoded2 = json_decode($s2, true);
                if (is_array($decoded2)) return $flatten($decoded2);
                elseif (is_string($decoded2)) {
                    $d3 = json_decode($decoded2, true);
                    if (is_array($d3)) return $flatten($d3);
                }
                if (preg_match_all('/"(?:\\\\.|[^"\\\\])*"/', $s, $m) && !empty($m[0])) {
                    $items = [];
                    foreach ($m[0] as $q) {
                        $qv = json_decode($q, true);
                        $items[] = is_string($qv) ? $qv : $q;
                    }
                    $items = array_values(array_filter($items, fn($v) => trim($v) !== ''));
                    if (!empty($items)) return $flatten($items);
                }
                if (strpos($s, "\n") !== false) $parts = preg_split('/\n+/', $s);
                elseif (strpos($s, ';') !== false) $parts = explode(';', $s);
                elseif (strpos($s, ',') !== false) $parts = explode(',', $s);
                else $parts = [$s];
                return $flatten($parts);
            }
            return [];
        };

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
            'etablissement_image' => $this->normalizeMediaPathFirst($profile->etablissement_image ?? null),
            'profile_image' => $this->normalizeMediaPathFirst($profile->profile_image ?? null),
            'horaires' => $horaires,
            'horaire_start' => $horaireStart,
            'horaire_end' => $horaireEnd,
            'services' => $services,
            'moyens_paiement' => isset($profile->moyens_paiement) ? $parseArray($profile->moyens_paiement) : [],
            'moyens_transport' => isset($profile->moyens_transport) ? $parseArray($profile->moyens_transport) : [],
            'jours_disponibles' => isset($profile->jours_disponibles) ? $parseArray($profile->jours_disponibles) : [],
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

    private function formatProfessionalProfile($user, $profile)
    {
        // Robust array parser: same as doctor, handles malformed JSON, unicode escapes, stray brackets, backslashes, and newlines
        $parseArray = function ($value) {
            $cleanOne = function ($s) {
                $s = (string) $s;
                $s = trim($s);
                if (strlen($s) >= 2 && ((($s[0] === '"') && substr($s, -1) === '"') || (($s[0] === "'") && substr($s, -1) === "'"))) {
                    $s = substr($s, 1, -1);
                }
                $s = str_replace(['\\r', '\\n'], ' ', $s);
                $s = str_replace(['\\"'], '"', $s);
                $s = str_replace(['\\\\'], '\\', $s);
                $s = str_replace(['\\/'], '/', $s);
                $s = rtrim($s, '\\');
                $s = trim($s, " \t\n\r\0\x0B\"'[]");
                if (preg_match('/\\\\u[0-9a-fA-F]{4}/', $s)) {
                    $s = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
                        $code = hexdec($m[1]);
                        return mb_convert_encoding(pack('n', $code), 'UTF-8', 'UTF-16BE');
                    }, $s);
                }
                return trim($s);
            };
            $flatten = function (array $list) use ($cleanOne) {
                $out = [];
                foreach ($list as $it) {
                    $parts = preg_split('/\\\\\s*|\r\n|\r|\n/', (string) $it);
                    foreach ($parts as $p) {
                        $p = $cleanOne($p);
                        if ($p !== '') $out[] = $p;
                    }
                }
                return array_values(array_unique($out));
            };
            if ($value === null) return [];
            if (is_array($value)) {
                $joined = trim(implode('', $value));
                $decoded = json_decode($joined, true);
                if (is_array($decoded)) return $flatten($decoded);
                $arr = [];
                foreach ($value as $item) {
                    if (is_string($item)) $arr[] = $item;
                    elseif (is_array($item)) foreach ($item as $sub) if (is_string($sub)) $arr[] = $sub;
                }
                return $flatten($arr);
            }
            if (is_string($value)) {
                $s = trim($value);
                if ($s === '') return [];
                $decoded = json_decode($s, true);
                if (is_array($decoded)) return $flatten($decoded);
                elseif (is_string($decoded)) {
                    $d2 = json_decode($decoded, true);
                    if (is_array($d2)) return $flatten($d2);
                }
                $s2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $s));
                $decoded2 = json_decode($s2, true);
                if (is_array($decoded2)) return $flatten($decoded2);
                elseif (is_string($decoded2)) {
                    $d3 = json_decode($decoded2, true);
                    if (is_array($d3)) return $flatten($d3);
                }
                if (preg_match_all('/"(?:\\\\.|[^"\\\\])*"/', $s, $m) && !empty($m[0])) {
                    $items = [];
                    foreach ($m[0] as $q) {
                        $qv = json_decode($q, true);
                        $items[] = is_string($qv) ? $qv : $q;
                    }
                    $items = array_values(array_filter($items, fn($v) => trim($v) !== ''));
                    if (!empty($items)) return $flatten($items);
                }
                if (strpos($s, "\n") !== false) $parts = preg_split('/\n+/', $s);
                elseif (strpos($s, ';') !== false) $parts = explode(';', $s);
                elseif (strpos($s, ',') !== false) $parts = explode(',', $s);
                else $parts = [$s];
                return $flatten($parts);
            }
            return [];
        };

        // Parse arrays
        $specialties = [];
        if (!empty($profile->specialty)) {
            $specialties = $parseArray($profile->specialty);
        }

        // Parse imgs: accept JSON array or comma-separated string; always return array of normalized paths
        $imgs = $this->normalizeMediaList($profile->imgs ?? null);

        // Build gallery: accept JSON array or CSV; include establishment/carte images as fallbacks
        $gallery = $this->normalizeMediaList($profile->gallery ?? null);
        if (empty($gallery) && !empty($profile->etablissement_image)) {
            $first = $this->normalizeMediaPathFirst($profile->etablissement_image);
            if ($first) $gallery[] = $first;
        }
        if (!empty($profile->carte_professionnelle)) {
            $cp = $this->normalizeMediaPathFirst($profile->carte_professionnelle);
            if ($cp) $gallery[] = $cp;
        }

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
            'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? null),
            'etablissement_image' => $this->normalizeMediaPath($profile->etablissement_image ?? null),
            'carte_professionnelle' => $this->normalizeMediaPath($profile->carte_professionnelle ?? null),
            'horaire_start' => $profile->horaire_start ?? null,
            'horaire_end' => $profile->horaire_end ?? null,
            'imgs' => $imgs,
            'gallery' => $gallery,
            'moyens_paiement' => isset($profile->moyens_paiement) ? $parseArray($profile->moyens_paiement) : [],
            'moyens_transport' => isset($profile->moyens_transport) ? $parseArray($profile->moyens_transport) : [],
            'jours_disponibles' => isset($profile->jours_disponibles) ? $parseArray($profile->jours_disponibles) : [],
            // Diplomas/Experiences: tolerant decode with non-empty fallback (diplomes -> diplomas)
            'diplomes' => (function() use ($profile) {
                $list = $this->parseObjectArrayLoose($profile->diplomes ?? null);
                if (empty($list)) {
                    $list = $this->parseObjectArrayLoose($profile->diplomas ?? null);
                }
                return $list;
            })(),
            'experiences' => $this->parseObjectArrayLoose($profile->experiences ?? null),
            'disponible' => $profile->disponible ?? true,
            'vacation_mode' => $profile->vacation_mode ?? false,
            'absence_start_date' => $profile->absence_start_date ?? null,
            'absence_end_date' => $profile->absence_end_date ?? null,
            'rdv_patients_suivis_uniquement' => $profile->rdv_patients_suivis_uniquement ?? false,
            'is_verified' => $user->is_verified ?? false,
            'created_at' => $user->created_at
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
     * Tolerant parser for arrays of JSON objects (e.g., diplomes, experiences).
     * Accepts arrays, single object, JSON strings (double-encoded, with CR/LF, single quotes, unquoted keys, trailing commas).
     */
    private function parseObjectArrayLoose($value)
    {
        if ($value === null) return [];

        if (is_array($value)) {
            $out = [];
            foreach ($value as $item) {
                if (is_array($item)) {
                    $out[] = $item;
                } elseif (is_string($item)) {
                    $d = json_decode($item, true);
                    if (is_array($d)) {
                        if (isset($d[0]) || empty($d)) {
                            foreach ($d as $el) if (is_array($el)) $out[] = $el;
                        } else {
                            $out[] = $d;
                        }
                    }
                }
            }
            return $out;
        }

        $tryDecode = function (string $s) {
            $j = json_decode($s, true);
            // Case 1: decoded directly to array (list or assoc)
            if (is_array($j)) {
                if (isset($j[0]) || empty($j)) {
                    $out = [];
                    foreach ($j as $el) if (is_array($el)) $out[] = $el;
                    return $out;
                }
                // Single object
                return [$j];
            }
            // Case 2: decoded to a string (double-encoded JSON), try decoding again
            if (is_string($j)) {
                $j2 = json_decode($j, true);
                if (is_array($j2)) {
                    if (isset($j2[0]) || empty($j2)) {
                        $out = [];
                        foreach ($j2 as $el) if (is_array($el)) $out[] = $el;
                        return $out;
                    }
                    return [$j2];
                }
            }
            return null;
        };

        if (is_string($value)) {
            $s = trim((string) $value);
            if ($s === '') return [];

            $out = $tryDecode($s);
            if ($out !== null) return $out;

            $s2 = stripcslashes(str_replace(["\r", "\n"], ["\\r", "\\n"], $s));
            $out = $tryDecode($s2);
            if ($out !== null) return $out;

            $fix = $s2;
            $fix = preg_replace('/,\s*([}\]])/', '$1', $fix);
            $fix = preg_replace('/([\{\s,])([A-Za-z0-9_]+)\s*:/', '$1"$2":', $fix);
            $fix = preg_replace_callback("/'(?:\\'|[^'])*'/", function ($m) {
                $inner = substr($m[0], 1, -1);
                $inner = str_replace(['\\"', '"'], ['"', '\\"'], $inner);
                return '"' . $inner . '"';
            }, $fix);
            $out = $tryDecode($fix);
            if ($out !== null) return $out;

            if (preg_match_all('/\{[^\{\}]*\}/s', $fix, $m) && !empty($m[0])) {
                $arr = [];
                foreach ($m[0] as $obj) {
                    $obj2 = preg_replace('/,\s*}/', '}', $obj);
                    $obj2 = preg_replace('/([\{\s,])([A-Za-z0-9_]+)\s*:/', '$1"$2":', $obj2);
                    $obj2 = preg_replace_callback("/'(?:\\'|[^'])*'/", function ($mm) {
                        $inner = substr($mm[0], 1, -1);
                        $inner = str_replace(['\\"', '"'], ['"', '\\"'], $inner);
                        return '"' . $inner . '"';
                    }, $obj2);
                    $d = json_decode($obj2, true);
                    if (is_array($d)) $arr[] = $d;
                }
                if (!empty($arr)) return $arr;
            }
        }

        return [];
    }

    /**
     * Normalize a stored media path to a web-accessible '/storage/...' URL path.
     */
    private function normalizeMediaPath($path)
    {
        if ($path === null || $path === '') {
            return $path;
        }
        $p = str_replace('\\', '/', trim((string) $path));
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
            $out = '/storage/' . ltrim($p2, '/');
            return preg_replace('#/+#', '/', $out);
        }
        // Heuristic: image files default to /storage
        if (preg_match('#\.(png|jpe?g|webp|gif|bmp|svg)$#i', $p2)) {
            $out = '/storage/' . ltrim($p2, '/');
            return preg_replace('#/+#', '/', $out);
        }
        // Fallback: ensure leading slash
        $out = '/' . ltrim($p2, '/');
        return preg_replace('#/+#', '/', $out);
    }

    /**
     * Normalize a list of media paths provided as JSON array or comma-separated string.
     * Always returns an array of normalized URLs.
     */
    private function normalizeMediaList($value): array
    {
        if ($value === null || $value === '') return [];

        // Already array
        if (is_array($value)) {
            $paths = $value;
        } else if (is_string($value)) {
            // Try JSON first
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $paths = $decoded;
            } else {
                // Split by comma (handles values like "a.jpg, b.jpg")
                $paths = preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            }
        } else {
            return [];
        }

        // Normalize each and filter empties; de-duplicate
        $out = [];
        foreach ($paths as $p) {
            if (!is_string($p)) continue;
            $n = $this->normalizeMediaPath($p);
            if ($n !== null && $n !== '' && !in_array($n, $out, true)) {
                $out[] = $n;
            }
        }
        return $out;
    }

    /**
     * Normalize a single media path, but if a list/CSV is provided, return the first valid entry.
     */
    private function normalizeMediaPathFirst($value): ?string
    {
        if ($value === null || $value === '') return $value;
        if (is_array($value)) {
            $arr = $this->normalizeMediaList($value);
            return $arr[0] ?? null;
        }
        if (is_string($value) && strpos($value, ',') !== false) {
            $arr = $this->normalizeMediaList($value);
            return $arr[0] ?? null;
        }
        return $this->normalizeMediaPath($value);
    }
}
