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
            $searchName = str_replace('-', ' ', $slug);

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
                    ->whereRaw("LOWER($tbl.$nameCol) LIKE ?", ['%' . strtolower($searchName) . '%'])
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
                        $has('horaire_start') ? "$tbl.horaire_start" : DB::raw("NULL as horaire_start"),
                        $has('horaire_end') ? "$tbl.horaire_end" : DB::raw("NULL as horaire_end"),
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
                        'horaire_start' => $row->horaire_start,
                        'horaire_end' => $row->horaire_end,
                        'rating' => (float)$row->rating,
                        'etablissement_image' => $row->etablissement_image,
                        'profile_image' => $row->profile_image,
                        'gallery' => $row->gallery ? (json_decode($row->gallery, true) ?: []) : [],
                        'imgs' => $row->imgs ? (json_decode($row->imgs, true) ?: []) : [],
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
                    ->whereRaw('LOWER(users.name) LIKE ?', ['%' . strtolower($searchName) . '%'])
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
                                'horaire_start' => $p->horaire_start,
                                'horaire_end' => $p->horaire_end,
                                'presentation' => $p->presentation,
                                'additional_info' => $p->additional_info,
                                'profile_image' => $p->profile_image,
                                'rating' => (float)$p->rating,
                                'imgs' => $p->imgs ? (json_decode($p->imgs, true) ?: []) : [],
                                'gallery' => $p->gallery ? (json_decode($p->gallery, true) ?: []) : [],
                                'moyens_paiement' => $p->moyens_paiement ? (json_decode($p->moyens_paiement, true) ?: []) : [],
                                'moyens_transport' => $p->moyens_transport ? (json_decode($p->moyens_transport, true) ?: []) : [],
                                'informations_pratiques' => $p->informations_pratiques,
                                'jours_disponibles' => $p->jours_disponibles ? (json_decode($p->jours_disponibles, true) ?: []) : [],
                                'contact_urgence' => $p->contact_urgence,
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
}
