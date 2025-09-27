<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\QueryException;

class ProfileSlugController extends Controller
{
    /**
     * Resolve any profile by slug across all types (professionals and organizations)
     * GET /api/profiles/slug/{slug}
     */
    public function showBySlug($slug)
    {
        try {
            // Early fallback: if an explicit numeric ID is provided as a query param,
            // return the profile by ID using the universal ProfileController.
            // This allows URLs like /profiles/slug/{slug}?id=149&type=medecin to work
            // even if the slug doesn't match exactly in production.
            $idParam = request()->query('id');
            if ($idParam !== null) {
                $id = (int)$idParam;
                if ($id > 0) {
                    return app(\App\Http\Controllers\ProfileController::class)->show($id);
                }
            } else {
                // Fallback: parse numeric id from Referer query (?id=...) for trusted frontend hosts
                $ref = request()->headers->get('referer');
                if (is_string($ref) && $ref !== '') {
                    $host = parse_url($ref, PHP_URL_HOST) ?: '';
                    $trustedHosts = [
                        'vi-santé.com',
                        'www.vi-santé.com',
                        'xn--vi-sant-hya.com', // punycode variant if ever used
                        'www.xn--vi-sant-hya.com',
                    ];
                    if (in_array(mb_strtolower($host, 'UTF-8'), $trustedHosts, true)) {
                        $qs = parse_url($ref, PHP_URL_QUERY);
                        if (is_string($qs) && $qs !== '') {
                            parse_str($qs, $qArr);
                            if (isset($qArr['id'])) {
                                $rid = (int)$qArr['id'];
                                if ($rid > 0) {
                                    return app(\App\Http\Controllers\ProfileController::class)->show($rid);
                                }
                            }
                        }
                    }
                }
            }

            // Normalize slug variants for robust matching
            $raw = urldecode((string)$slug);
            $slugLower = mb_strtolower($raw, 'UTF-8');
            $searchName = str_replace('-', ' ', $slugLower); // legacy behavior
            $searchHyphen = str_replace([' ', '_'], '-', $slugLower);
            $searchSpace = str_replace(['-', '_'], ' ', $slugLower);
            $searchNoSep = str_replace([' ', '-', '_'], '', $slugLower);

            // 1) Try organizations first (pharmacie, parapharmacie, clinique, labo, radiologie)
            $orgs = [
                ['table' => 'pharmacie_profiles', 'name_col' => 'nom_pharmacie', 'type' => 'pharmacie'],
                ['table' => 'parapharmacie_profiles', 'name_col' => 'nom_parapharmacie', 'type' => 'parapharmacie'],
                ['table' => 'clinique_profiles', 'name_col' => 'nom_clinique', 'type' => 'clinique'],
                ['table' => 'labo_analyse_profiles', 'name_col' => 'nom_labo', 'type' => 'labo_analyse'],
                ['table' => 'centre_radiologie_profiles', 'name_col' => 'nom_centre', 'type' => 'centre_radiologie'],
            ];

            foreach ($orgs as $o) {
                $tbl = $o['table'];
                $nameCol = $o['name_col'];
                $type = $o['type'];
                if (!Schema::hasTable($tbl)) continue;
                if (!Schema::hasColumn($tbl, $nameCol)) continue;

                $has = function ($col) use ($tbl) { return Schema::hasColumn($tbl, $col); };

                $row = DB::table($tbl)
                    ->join('users', "$tbl.user_id", '=', 'users.id')
                    ->where(function ($q) use ($tbl, $nameCol, $searchSpace, $searchHyphen, $searchNoSep) {
                        // Exact normalized equality first (safest)
                        $q->whereRaw(
                            "LOWER(REPLACE(REPLACE(REPLACE($tbl.$nameCol, ' ', ''), '-', ''), '_', '')) = ?",
                            [$searchNoSep]
                        )
                        // Then safe LIKE variants
                        ->orWhereRaw("LOWER($tbl.$nameCol) LIKE ?", ['%' . $searchSpace . '%'])
                        ->orWhereRaw("LOWER($tbl.$nameCol) LIKE ?", ['%' . $searchHyphen . '%'])
                        ->orWhereRaw(
                            "LOWER(REPLACE(REPLACE(REPLACE($tbl.$nameCol, ' ', ''), '-', ''), '_', '')) LIKE ?",
                            ['%' . $searchNoSep . '%']
                        );
                    })
                    ->select(
                        'users.id', 'users.name', 'users.email', 'users.phone', 'users.is_verified',
                        DB::raw("$tbl.id as profile_id"),
                        DB::raw("$tbl.$nameCol as org_name"),
                        "$tbl.adresse",
                        $has('ville') ? "$tbl.ville" : DB::raw("NULL as ville"),
                        $has('services') ? "$tbl.services" : DB::raw("NULL as services"),
                        $has('description') ? "$tbl.description" : DB::raw("NULL as description"),
                        $has('org_presentation') ? "$tbl.org_presentation" : DB::raw("NULL as org_presentation"),
                        $has('services_description') ? "$tbl.services_description" : DB::raw("NULL as services_description"),
                        $has('responsable_name') ? "$tbl.responsable_name" : ($has('gerant_name') ? DB::raw("$tbl.gerant_name as responsable_name") : DB::raw("NULL as responsable_name")),
                        $has('horaires') ? "$tbl.horaires" : DB::raw("NULL as horaires"),
                        $has('horaire_start') ? "$tbl.horaire_start" : DB::raw("NULL as horaire_start"),
                        $has('horaire_end') ? "$tbl.horaire_end" : DB::raw("NULL as horaire_end"),
                        $has('moyens_paiement') ? "$tbl.moyens_paiement" : DB::raw("NULL as moyens_paiement"),
                        $has('moyens_transport') ? "$tbl.moyens_transport" : DB::raw("NULL as moyens_transport"),
                        $has('informations_pratiques') ? "$tbl.informations_pratiques" : DB::raw("NULL as informations_pratiques"),
                        $has('jours_disponibles') ? "$tbl.jours_disponibles" : DB::raw("NULL as jours_disponibles"),
                        $has('contact_urgence') ? "$tbl.contact_urgence" : DB::raw("NULL as contact_urgence"),
                        $has('rating') ? "$tbl.rating" : DB::raw("0 as rating"),
                        $has('guard') ? "$tbl.guard" : DB::raw("0 as guard"),
                        $has('etablissement_image') ? "$tbl.etablissement_image" : DB::raw("NULL as etablissement_image"),
                        $has('profile_image') ? "$tbl.profile_image" : DB::raw("NULL as profile_image"),
                        $has('gallery') ? "$tbl.gallery" : DB::raw("NULL as gallery"),
                        $has('imgs') ? "$tbl.imgs" : DB::raw("NULL as imgs"),
                        $has('disponible') ? "$tbl.disponible" : DB::raw("1 as disponible"),
                        $has('vacation_mode') ? "$tbl.vacation_mode" : DB::raw("0 as vacation_mode"),
                        $has('vacation_auto_reactivate_date') ? "$tbl.vacation_auto_reactivate_date" : DB::raw("NULL as vacation_auto_reactivate_date"),
                        $has('absence_start_date') ? "$tbl.absence_start_date" : DB::raw("NULL as absence_start_date"),
                        $has('absence_end_date') ? "$tbl.absence_end_date" : DB::raw("NULL as absence_end_date"),
                        "$tbl.created_at", "$tbl.updated_at"
                    )
                    ->first();

                if ($row) {
                    $resp = [
                        'id' => $row->id,
                        'name' => $row->name,
                        'type' => $type,
                        'adresse' => $row->adresse,
                        'ville' => $row->ville,
                        'services' => $row->services ? (json_decode($row->services, true) ?: []) : [],
                        'description' => $row->description,
                        'org_presentation' => $row->org_presentation,
                        'services_description' => $row->services_description,
                        'responsable_name' => $row->responsable_name,
                        // horaires and start/end (fallback from horaires if needed)
                        'horaires' => $row->horaires ? (json_decode($row->horaires, true) ?: $row->horaires) : null,
                        'horaire_start' => $row->horaire_start ?: (function() use ($row) {
                            if (!empty($row->horaires)) { $h = json_decode($row->horaires, true); if (is_array($h) && isset($h['start'])) return $h['start']; }
                            return null;
                        })(),
                        'horaire_end' => $row->horaire_end ?: (function() use ($row) {
                            if (!empty($row->horaires)) { $h = json_decode($row->horaires, true); if (is_array($h) && isset($h['end'])) return $h['end']; }
                            return null;
                        })(),
                        // practical fields
                        'moyens_paiement' => $row->moyens_paiement ? (json_decode($row->moyens_paiement, true) ?: []) : [],
                        'moyens_transport' => $row->moyens_transport ? (json_decode($row->moyens_transport, true) ?: []) : [],
                        'informations_pratiques' => $row->informations_pratiques,
                        'jours_disponibles' => $row->jours_disponibles ? (json_decode($row->jours_disponibles, true) ?: []) : [],
                        'contact_urgence' => $row->contact_urgence,
                        'rating' => (float)$row->rating,
                        'etablissement_image' => $this->normalizeMediaPath($row->etablissement_image),
                        'profile_image' => $this->normalizeMediaPath($row->profile_image),
                        'gallery' => (function() use ($row) {
                            if (!$row->gallery) return [];
                            $g = json_decode($row->gallery, true);
                            if (is_array($g)) return array_map(function($p){ return $this->normalizeMediaPath($p); }, $g);
                            if (is_string($row->gallery)) return [$this->normalizeMediaPath($row->gallery)];
                            return [];
                        }).call($this),
                        'imgs' => (function() use ($row) {
                            if (!$row->imgs) return [];
                            $g = json_decode($row->imgs, true);
                            return is_array($g) ? array_map(function($p){ return $this->normalizeMediaPath($p); }, $g) : [];
                        }).call($this),
                        'disponible' => (bool)$row->disponible,
                        'vacation_mode' => (bool)$row->vacation_mode,
                        'vacation_auto_reactivate_date' => $row->vacation_auto_reactivate_date,
                        'absence_start_date' => $row->absence_start_date,
                        'absence_end_date' => $row->absence_end_date,
                        'email' => $row->email,
                        'phone' => $row->phone,
                        'is_verified' => (bool)$row->is_verified,
                        'created_at' => $row->created_at,
                        'updated_at' => $row->updated_at,
                        // Convenience
                        'org_name' => $row->org_name,
                    ];
                    if ($type === 'pharmacie') $resp['nom_pharmacie'] = $row->org_name;
                    if ($type === 'parapharmacie') $resp['nom_parapharmacie'] = $row->org_name;
                    if ($type === 'clinique') $resp['nom_clinique'] = $row->org_name;
                    if ($type === 'labo_analyse') $resp['nom_labo'] = $row->org_name;
                    if ($type === 'centre_radiologie') $resp['nom_centre'] = $row->org_name;
                    if (property_exists($row, 'guard')) $resp['guard'] = (bool)$row->guard;
                    return response()->json($resp);
                }
            }

            // 2) Try individual professionals (match users.name)
            if (Schema::hasTable('users')) {
                $user = DB::table('users')
                    ->where(function ($q) use ($searchSpace, $searchHyphen, $searchNoSep) {
                        $q->whereRaw(
                            "LOWER(REPLACE(REPLACE(REPLACE(users.name, ' ', ''), '-', ''), '_', '')) = ?",
                            [$searchNoSep]
                        )
                        ->orWhereRaw('LOWER(users.name) LIKE ?', ['%' . $searchSpace . '%'])
                        ->orWhereRaw('LOWER(users.name) LIKE ?', ['%' . $searchHyphen . '%'])
                        ->orWhereRaw(
                            "LOWER(REPLACE(REPLACE(REPLACE(users.name, ' ', ''), '-', ''), '_', '')) LIKE ?",
                            ['%' . $searchNoSep . '%']
                        );
                    })
                    ->select('id', 'name', 'email', 'phone', 'is_verified', 'created_at')
                    ->first();

                if ($user) {
                    $uid = $user->id;
                    $profTables = [
                        ['tbl' => 'medecin_profiles', 'type' => 'medecin'],
                        ['tbl' => 'kine_profiles', 'type' => 'kine'],
                        ['tbl' => 'orthophoniste_profiles', 'type' => 'orthophoniste'],
                        ['tbl' => 'psychologue_profiles', 'type' => 'psychologue'],
                    ];

                    foreach ($profTables as $pt) {
                        $t = $pt['tbl'];
                        if (!Schema::hasTable($t)) continue;
                        $has = function ($c) use ($t) { return Schema::hasColumn($t, $c); };

                        $p = DB::table($t)
                            ->where('user_id', $uid)
                            ->select(
                                DB::raw("'{$pt['type']}' as ptype"),
                                $has('specialty') ? "$t.specialty" : DB::raw("NULL as specialty"),
                                $has('experience_years') ? "$t.experience_years" : DB::raw("NULL as experience_years"),
                                $has('adresse') ? "$t.adresse" : DB::raw("NULL as adresse"),
                                $has('ville') ? "$t.ville" : DB::raw("NULL as ville"),
                                $has('horaires') ? "$t.horaires" : DB::raw("NULL as horaires"),
                                $has('horaire_start') ? "$t.horaire_start" : DB::raw("NULL as horaire_start"),
                                $has('horaire_end') ? "$t.horaire_end" : DB::raw("NULL as horaire_end"),
                                $has('presentation') ? "$t.presentation" : DB::raw("NULL as presentation"),
                                $has('additional_info') ? "$t.additional_info" : DB::raw("NULL as additional_info"),
                                $has('profile_image') ? "$t.profile_image" : DB::raw("NULL as profile_image"),
                                $has('rating') ? "$t.rating" : DB::raw("0 as rating"),
                                $has('imgs') ? "$t.imgs" : DB::raw("NULL as imgs"),
                                $has('gallery') ? "$t.gallery" : DB::raw("NULL as gallery"),
                                $has('moyens_paiement') ? "$t.moyens_paiement" : DB::raw("NULL as moyens_paiement"),
                                $has('moyens_transport') ? "$t.moyens_transport" : DB::raw("NULL as moyens_transport"),
                                $has('informations_pratiques') ? "$t.informations_pratiques" : DB::raw("NULL as informations_pratiques"),
                                $has('jours_disponibles') ? "$t.jours_disponibles" : DB::raw("NULL as jours_disponibles"),
                                $has('contact_urgence') ? "$t.contact_urgence" : DB::raw("NULL as contact_urgence"),
                                $has('diplomes') ? "$t.diplomes" : DB::raw("NULL as diplomes"),
                                $has('experiences') ? "$t.experiences" : DB::raw("NULL as experiences"),
                                $has('disponible') ? "$t.disponible" : DB::raw("1 as disponible")
                            )
                            ->first();

                        if ($p) {
                            return response()->json([
                                'id' => $uid,
                                'name' => $user->name,
                                'type' => $pt['type'],
                                'specialty' => $p->specialty,
                                'experience_years' => $p->experience_years,
                                'adresse' => $p->adresse,
                                'ville' => $p->ville,
                                // Include horaires JSON and derive start/end if missing
                                'horaires' => $p->horaires ? (json_decode($p->horaires, true) ?: $p->horaires) : null,
                                'horaire_start' => (function() use ($p) {
                                    if (!empty($p->horaire_start)) return $p->horaire_start;
                                    if (!empty($p->horaires)) {
                                        $h = json_decode($p->horaires, true);
                                        if (is_array($h) && isset($h['start'])) return $h['start'];
                                    }
                                    return null;
                                })(),
                                'horaire_end' => (function() use ($p) {
                                    if (!empty($p->horaire_end)) return $p->horaire_end;
                                    if (!empty($p->horaires)) {
                                        $h = json_decode($p->horaires, true);
                                        if (is_array($h) && isset($h['end'])) return $h['end'];
                                    }
                                    return null;
                                })(),
                                'presentation' => $p->presentation,
                                'additional_info' => $p->additional_info,
                                'profile_image' => $this->normalizeMediaPath($p->profile_image),
                                'rating' => (float)$p->rating,
                                'imgs' => (function() use ($p) {
                                    if (!$p->imgs) return [];
                                    $arr = json_decode($p->imgs, true);
                                    return is_array($arr) ? array_map(function($x){ return $this->normalizeMediaPath($x); }, $arr) : [];
                                }).call($this),
                                'gallery' => (function() use ($p) {
                                    if (!$p->gallery) return [];
                                    $g = json_decode($p->gallery, true);
                                    if (is_array($g)) return array_map(function($x){ return $this->normalizeMediaPath($x); }, $g);
                                    if (is_string($p->gallery)) return [$this->normalizeMediaPath($p->gallery)];
                                    return [];
                                }).call($this),
                                'moyens_paiement' => $p->moyens_paiement ? (json_decode($p->moyens_paiement, true) ?: []) : [],
                                'moyens_transport' => $p->moyens_transport ? (json_decode($p->moyens_transport, true) ?: []) : [],
                                'informations_pratiques' => $p->informations_pratiques,
                                'jours_disponibles' => $p->jours_disponibles ? (json_decode($p->jours_disponibles, true) ?: []) : [],
                                'contact_urgence' => $p->contact_urgence,
                                // CV fields
                                'diplomes' => $p->diplomes ? (json_decode($p->diplomes, true) ?: $p->diplomes) : [],
                                'experiences' => $p->experiences ? (json_decode($p->experiences, true) ?: $p->experiences) : [],
                                'disponible' => (bool)$p->disponible,
                                'email' => $user->email,
                                'phone' => $user->phone,
                                'is_verified' => (bool)$user->is_verified,
                                'created_at' => $user->created_at,
                            ]);
                        }
                    }
                }
            }

            return response()->json(['message' => 'Profile not found'], 404);
        } catch (QueryException $e) {
            Log::error('DB error resolving profile by slug', [
                'slug' => $slug,
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'code' => $e->getCode(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error fetching profile by slug'], 500);
        } catch (\Exception $e) {
            Log::error('Error resolving profile by slug', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error fetching profile by slug'], 500);
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
}
