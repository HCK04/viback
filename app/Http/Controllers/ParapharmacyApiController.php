<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;

class ParapharmacyApiController extends Controller
{
    /**
     * List all parapharmacies (public)
     */
    public function index(Request $request)
    {
        try {
            if (!Schema::hasTable('parapharmacie_profiles')) {
                Log::error('parapharmacie_profiles table is missing');
                // Return empty list to avoid breaking clients
                return response()->json([]);
            }
            $tbl = 'parapharmacie_profiles';
            $has = function ($col) use ($tbl) { return Schema::hasColumn($tbl, $col); };
            $hasUser = function ($col) { return Schema::hasColumn('users', $col); };
            $select = [
                'users.id',
                DB::raw("$tbl.id as parapharmacie_id"),
                "$tbl.nom_parapharmacie",
                DB::raw("$tbl.nom_parapharmacie as name"),
                "$tbl.adresse",
                $has('ville') ? "$tbl.ville" : DB::raw("NULL as ville"),
                $has('services') ? "$tbl.services" : DB::raw("NULL as services"),
                $has('description') ? "$tbl.description" : DB::raw("NULL as description"),
                $has('org_presentation') ? "$tbl.org_presentation" : DB::raw("NULL as org_presentation"),
                $has('services_description') ? "$tbl.services_description" : DB::raw("NULL as services_description"),
                $has('responsable_name') ? "$tbl.responsable_name" : ($has('gerant_name') ? DB::raw("$tbl.gerant_name as responsable_name") : DB::raw("NULL as responsable_name")),
                $has('horaire_start') ? "$tbl.horaire_start" : DB::raw("NULL as horaire_start"),
                $has('horaire_end') ? "$tbl.horaire_end" : DB::raw("NULL as horaire_end"),
                $has('rating') ? "$tbl.rating" : DB::raw("0 as rating"),
                $has('etablissement_image') ? "$tbl.etablissement_image" : DB::raw("NULL as etablissement_image"),
                $has('profile_image') ? "$tbl.profile_image" : DB::raw("NULL as profile_image"),
                $has('gallery') ? "$tbl.gallery" : DB::raw("NULL as gallery"),
                $has('imgs') ? "$tbl.imgs" : DB::raw("NULL as imgs"),
                $has('guard') ? "$tbl.guard" : DB::raw("0 as guard"),
                $has('disponible') ? "$tbl.disponible" : DB::raw("1 as disponible"),
                $has('vacation_mode') ? "$tbl.vacation_mode" : DB::raw("0 as vacation_mode"),
                $has('absence_start_date') ? "$tbl.absence_start_date" : DB::raw("NULL as absence_start_date"),
                $has('absence_end_date') ? "$tbl.absence_end_date" : DB::raw("NULL as absence_end_date"),
                $has('vacation_auto_reactivate_date') ? "$tbl.vacation_auto_reactivate_date" : DB::raw("NULL as vacation_auto_reactivate_date"),
                $hasUser('email') ? 'users.email' : DB::raw("NULL as email"),
                $hasUser('phone') ? 'users.phone' : DB::raw("NULL as phone"),
                $hasUser('is_verified') ? 'users.is_verified' : DB::raw("0 as is_verified"),
                "$tbl.created_at",
                "$tbl.updated_at",
            ];

            $rows = DB::table('parapharmacie_profiles')
                ->join('users', 'parapharmacie_profiles.user_id', '=', 'users.id')
                ->select($select)
                ->get();

            $data = $rows->map(function ($p) {
                return [
                    'id' => $p->id, // user id
                    'parapharmacie_id' => $p->parapharmacie_id,
                    'nom_parapharmacie' => $p->nom_parapharmacie,
                    'name' => $p->name,
                    'adresse' => $p->adresse,
                    'ville' => $p->ville,
                    'services' => json_decode($p->services, true) ?: [],
                    'description' => $p->description,
                    'org_presentation' => $p->org_presentation,
                    'services_description' => $p->services_description,
                    'responsable_name' => $p->responsable_name,
                    'horaire_start' => $p->horaire_start,
                    'horaire_end' => $p->horaire_end,
                    'rating' => (string) $p->rating,
                    'etablissement_image' => $p->etablissement_image,
                    'profile_image' => $p->profile_image,
                    'gallery' => json_decode($p->gallery, true) ?: [],
                    'imgs' => json_decode($p->imgs, true) ?: [],
                    'guard' => (bool) $p->guard,
                    'disponible' => (bool) $p->disponible,
                    'vacation_mode' => (bool) $p->vacation_mode,
                    'absence_start_date' => $p->absence_start_date,
                    'absence_end_date' => $p->absence_end_date,
                    'vacation_auto_reactivate_date' => $p->vacation_auto_reactivate_date,
                    'type' => 'parapharmacie',
                    'email' => $p->email,
                    'phone' => $p->phone,
                    'is_verified' => (bool) $p->is_verified,
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ];
            })->toArray();

            return response()->json($data);
        } catch (QueryException $e) {
            Log::error('DB error fetching parapharmacies', [
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'code' => $e->getCode(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error fetching parapharmacies'], 500);
        } catch (\Exception $e) {
            Log::error('Error fetching parapharmacies', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Error fetching parapharmacies'], 500);
        }
    }

    /**
     * Search parapharmacies by city (public)
     */
    public function searchByCity(Request $request)
    {
        try {
            if (!Schema::hasTable('parapharmacie_profiles')) {
                Log::error('parapharmacie_profiles table is missing');
                return response()->json([]);
            }
            $ville = $request->get('ville');
            $tbl = 'parapharmacie_profiles';
            $has = function ($col) use ($tbl) { return Schema::hasColumn($tbl, $col); };
            $hasUser = function ($col) { return Schema::hasColumn('users', $col); };

            $select = [
                'users.id',
                DB::raw("$tbl.id as parapharmacie_id"),
                "$tbl.nom_parapharmacie",
                DB::raw("$tbl.nom_parapharmacie as name"),
                "$tbl.adresse",
                $has('ville') ? "$tbl.ville" : DB::raw("NULL as ville"),
                $has('services') ? "$tbl.services" : DB::raw("NULL as services"),
                $has('description') ? "$tbl.description" : DB::raw("NULL as description"),
                $has('org_presentation') ? "$tbl.org_presentation" : DB::raw("NULL as org_presentation"),
                $has('services_description') ? "$tbl.services_description" : DB::raw("NULL as services_description"),
                $has('responsable_name') ? "$tbl.responsable_name" : ($has('gerant_name') ? DB::raw("$tbl.gerant_name as responsable_name") : DB::raw("NULL as responsable_name")),
                $has('horaire_start') ? "$tbl.horaire_start" : DB::raw("NULL as horaire_start"),
                $has('horaire_end') ? "$tbl.horaire_end" : DB::raw("NULL as horaire_end"),
                $has('rating') ? "$tbl.rating" : DB::raw("0 as rating"),
                $has('etablissement_image') ? "$tbl.etablissement_image" : DB::raw("NULL as etablissement_image"),
                $has('profile_image') ? "$tbl.profile_image" : DB::raw("NULL as profile_image"),
                $has('gallery') ? "$tbl.gallery" : DB::raw("NULL as gallery"),
                $has('imgs') ? "$tbl.imgs" : DB::raw("NULL as imgs"),
                $has('guard') ? "$tbl.guard" : DB::raw("0 as guard"),
                $has('disponible') ? "$tbl.disponible" : DB::raw("1 as disponible"),
                $has('vacation_mode') ? "$tbl.vacation_mode" : DB::raw("0 as vacation_mode"),
                $has('absence_start_date') ? "$tbl.absence_start_date" : DB::raw("NULL as absence_start_date"),
                $has('absence_end_date') ? "$tbl.absence_end_date" : DB::raw("NULL as absence_end_date"),
                $has('vacation_auto_reactivate_date') ? "$tbl.vacation_auto_reactivate_date" : DB::raw("NULL as vacation_auto_reactivate_date"),
                $hasUser('email') ? 'users.email' : DB::raw("NULL as email"),
                $hasUser('phone') ? 'users.phone' : DB::raw("NULL as phone"),
                $hasUser('is_verified') ? 'users.is_verified' : DB::raw("0 as is_verified"),
                "$tbl.created_at",
                "$tbl.updated_at"
            ];

            $query = DB::table($tbl)
                ->join('users', "$tbl.user_id", '=', 'users.id')
                ->select($select);

            if ($ville && $ville !== 'Toutes les villes' && $has('ville')) {
                $query->where("$tbl.ville", 'LIKE', "%{$ville}%");
            }

            $rows = $query->get();

            $data = $rows->map(function ($p) {
                return [
                    'id' => $p->id,
                    'parapharmacie_id' => $p->parapharmacie_id,
                    'name' => $p->name,
                    'nom_parapharmacie' => $p->nom_parapharmacie,
                    'adresse' => $p->adresse,
                    'ville' => $p->ville,
                    'services' => json_decode($p->services, true) ?: [],
                    'description' => $p->description,
                    'org_presentation' => $p->org_presentation,
                    'services_description' => $p->services_description,
                    'responsable_name' => $p->responsable_name,
                    'horaire_start' => $p->horaire_start,
                    'horaire_end' => $p->horaire_end,
                    'rating' => (string) $p->rating,
                    'etablissement_image' => $p->etablissement_image,
                    'profile_image' => $p->profile_image,
                    'gallery' => json_decode($p->gallery, true) ?: [],
                    'imgs' => json_decode($p->imgs, true) ?: [],
                    'guard' => (bool) $p->guard,
                    'disponible' => (bool) $p->disponible,
                    'vacation_mode' => (bool) $p->vacation_mode,
                    'absence_start_date' => $p->absence_start_date,
                    'absence_end_date' => $p->absence_end_date,
                    'vacation_auto_reactivate_date' => $p->vacation_auto_reactivate_date,
                    'type' => 'parapharmacie',
                    'email' => $p->email,
                    'phone' => $p->phone,
                    'is_verified' => (bool) $p->is_verified,
                    'created_at' => $p->created_at,
                    'updated_at' => $p->updated_at,
                ];
            })->toArray();

            return response()->json($data);
        } catch (QueryException $e) {
            Log::error('DB error searching parapharmacies by city', [
                'ville' => $ville ?? 'N/A',
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'code' => $e->getCode(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error searching parapharmacies'], 500);
        } catch (\Exception $e) {
            Log::error('Error searching parapharmacies by city', [
                'ville' => $ville ?? 'N/A',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error searching parapharmacies'], 500);
        }
    }

    /**
     * Get a single parapharmacy by user ID (public profile)
     */
    public function show($id)
    {
        try {
            if (!Schema::hasTable('parapharmacie_profiles')) {
                Log::error('parapharmacie_profiles table is missing');
                return response()->json(['message' => 'Parapharmacie not found'], 404);
            }
            $tbl = 'parapharmacie_profiles';
            // 1) Fetch profile by user_id OR by profile id
            $profile = DB::table($tbl)
                ->where("$tbl.user_id", $id)
                ->orWhere("$tbl.id", $id)
                ->first();

            if (!$profile) {
                return response()->json(['message' => 'Parapharmacie not found'], 404);
            }

            // 2) Fetch user separately for robust compatibility across schemas
            $user = null;
            if (Schema::hasTable('users')) {
                $hasUser = function ($col) { return Schema::hasColumn('users', $col); };
                $user = DB::table('users')
                    ->where('id', $profile->user_id)
                    ->select(
                        'id',
                        'name',
                        $hasUser('email') ? 'email' : DB::raw('NULL as email'),
                        $hasUser('phone') ? 'phone' : DB::raw('NULL as phone'),
                        $hasUser('is_verified') ? 'is_verified' : DB::raw('0 as is_verified')
                    )
                    ->first();
            }

            // 3) Build safe response
            $resp = [
                'id' => $user->id ?? $profile->user_id,
                'parapharmacie_id' => $profile->id ?? null,
                'name' => $user->name ?? null,
                'nom_parapharmacie' => $profile->nom_parapharmacie ?? null,
                'adresse' => $profile->adresse ?? null,
                'ville' => $profile->ville ?? null,
                'services' => isset($profile->services) ? (json_decode($profile->services, true) ?: []) : [],
                'description' => $profile->description ?? null,
                'org_presentation' => $profile->org_presentation ?? null,
                'services_description' => $profile->services_description ?? null,
                'responsable_name' => $profile->responsable_name ?? ($profile->gerant_name ?? null),
                'horaire_start' => $profile->horaire_start ?? null,
                'horaire_end' => $profile->horaire_end ?? null,
                'rating' => isset($profile->rating) ? (float) $profile->rating : 0.0,
                'moyens_paiement' => isset($profile->moyens_paiement) ? (json_decode($profile->moyens_paiement, true) ?: []) : [],
                'moyens_transport' => isset($profile->moyens_transport) ? (json_decode($profile->moyens_transport, true) ?: []) : [],
                'informations_pratiques' => $profile->informations_pratiques ?? null,
                'jours_disponibles' => isset($profile->jours_disponibles) ? (json_decode($profile->jours_disponibles, true) ?: []) : [],
                'contact_urgence' => $profile->contact_urgence ?? null,
                'etablissement_image' => $profile->etablissement_image ?? null,
                'profile_image' => $profile->profile_image ?? null,
                'gallery' => isset($profile->gallery) ? (json_decode($profile->gallery, true) ?: []) : [],
                'imgs' => isset($profile->imgs) ? (json_decode($profile->imgs, true) ?: []) : [],
                'disponible' => isset($profile->disponible) ? (bool) $profile->disponible : true,
                'vacation_mode' => isset($profile->vacation_mode) ? (bool) $profile->vacation_mode : false,
                'vacation_auto_reactivate_date' => $profile->vacation_auto_reactivate_date ?? null,
                'absence_start_date' => $profile->absence_start_date ?? null,
                'absence_end_date' => $profile->absence_end_date ?? null,
                'additional_info' => $profile->additional_info ?? null,
                'type' => 'parapharmacie',
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
                'is_verified' => (bool)($user->is_verified ?? 0),
                'created_at' => $profile->created_at ?? null,
                'updated_at' => $profile->updated_at ?? null,
            ];

            return response()->json($resp);
        } catch (QueryException $e) {
            Log::error('DB error fetching parapharmacie profile', [
                'id' => $id,
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'code' => $e->getCode(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error fetching parapharmacie profile'], 500);
        } catch (\Exception $e) {
            Log::error('Error fetching parapharmacie profile', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['error' => 'Error fetching parapharmacie profile'], 500);
        }
    }

    /**
     * Show parapharmacy by slug (optional helper)
     */
    public function showBySlug($slug)
    {
        try {
            if (!Schema::hasTable('parapharmacie_profiles')) {
                Log::error('parapharmacie_profiles table is missing');
                return response()->json(['message' => 'Parapharmacie not found'], 404);
            }
            $searchName = str_replace('-', ' ', $slug);
            $tbl = 'parapharmacie_profiles';
            $has = function ($col) use ($tbl) { return Schema::hasColumn($tbl, $col); };
            $p = DB::table($tbl)
                ->join('users', "$tbl.user_id", '=', 'users.id')
                ->where(function ($q) use ($searchName, $tbl) {
                    $q->whereRaw("LOWER($tbl.nom_parapharmacie) LIKE ?", ['%' . strtolower($searchName) . '%'])
                      ->orWhereRaw('LOWER(users.name) LIKE ?', ['%' . strtolower($searchName) . '%']);
                })
                ->select(
                    'users.id', 'users.name',
                    DB::raw("$tbl.id as parapharmacie_id"),
                    "$tbl.nom_parapharmacie",
                    "$tbl.adresse",
                    $has('ville') ? "$tbl.ville" : DB::raw("NULL as ville"),
                    $has('services') ? "$tbl.services" : DB::raw("NULL as services"),
                    $has('description') ? "$tbl.description" : DB::raw("NULL as description"),
                    $has('org_presentation') ? "$tbl.org_presentation" : DB::raw("NULL as org_presentation"),
                    $has('services_description') ? "$tbl.services_description" : DB::raw("NULL as services_description"),
                    $has('responsable_name') ? "$tbl.responsable_name" : ($has('gerant_name') ? DB::raw("$tbl.gerant_name as responsable_name") : DB::raw("NULL as responsable_name")),
                    $has('horaire_start') ? "$tbl.horaire_start" : DB::raw("NULL as horaire_start"),
                    $has('horaire_end') ? "$tbl.horaire_end" : DB::raw("NULL as horaire_end"),
                    $has('rating') ? "$tbl.rating" : DB::raw("0 as rating"),
                    $has('etablissement_image') ? "$tbl.etablissement_image" : DB::raw("NULL as etablissement_image"),
                    $has('profile_image') ? "$tbl.profile_image" : DB::raw("NULL as profile_image"),
                    $has('gallery') ? "$tbl.gallery" : DB::raw("NULL as gallery"),
                    $has('imgs') ? "$tbl.imgs" : DB::raw("NULL as imgs"),
                    $has('disponible') ? "$tbl.disponible" : DB::raw("1 as disponible"),
                    $has('vacation_mode') ? "$tbl.vacation_mode" : DB::raw("0 as vacation_mode"),
                    $has('vacation_auto_reactivate_date') ? "$tbl.vacation_auto_reactivate_date" : DB::raw("NULL as vacation_auto_reactivate_date"),
                    $has('absence_start_date') ? "$tbl.absence_start_date" : DB::raw("NULL as absence_start_date"),
                    $has('absence_end_date') ? "$tbl.absence_end_date" : DB::raw("NULL as absence_end_date"),
                    ($hasUser('email') ? 'users.email' : DB::raw("NULL as email")),
                    ($hasUser('phone') ? 'users.phone' : DB::raw("NULL as phone")),
                    ($hasUser('is_verified') ? 'users.is_verified' : DB::raw("0 as is_verified")),
                    "$tbl.created_at", "$tbl.updated_at"
                )
                ->first();

            if (!$p) {
                return response()->json(['message' => 'Parapharmacie not found'], 404);
            }

            $resp = [
                'id' => $p->id,
                'parapharmacie_id' => $p->parapharmacie_id,
                'name' => $p->name,
                'nom_parapharmacie' => $p->nom_parapharmacie,
                'adresse' => $p->adresse,
                'ville' => $p->ville,
                'services' => json_decode($p->services, true) ?: [],
                'description' => $p->description,
                'org_presentation' => $p->org_presentation,
                'services_description' => $p->services_description,
                'responsable_name' => $p->responsable_name,
                'horaire_start' => $p->horaire_start,
                'horaire_end' => $p->horaire_end,
                'rating' => (float) $p->rating,
                'etablissement_image' => $p->etablissement_image,
                'profile_image' => $p->profile_image,
                'gallery' => json_decode($p->gallery, true) ?: [],
                'imgs' => json_decode($p->imgs, true) ?: [],
                'disponible' => (bool) $p->disponible,
                'vacation_mode' => (bool) $p->vacation_mode,
                'vacation_auto_reactivate_date' => $p->vacation_auto_reactivate_date,
                'absence_start_date' => $p->absence_start_date,
                'absence_end_date' => $p->absence_end_date,
                'type' => 'parapharmacie',
                'email' => $p->email,
                'phone' => $p->phone,
                'is_verified' => (bool) $p->is_verified,
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at
            ];

            return response()->json($resp);
        } catch (QueryException $e) {
            Log::error('DB error fetching parapharmacie by slug', [
                'slug' => $slug,
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'code' => $e->getCode(),
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error fetching parapharmacie profile'], 500);
        } catch (\Exception $e) {
            Log::error('Error fetching parapharmacie by slug', ['slug' => $slug, 'error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Error fetching parapharmacie profile'], 500);
        }
    }
}
