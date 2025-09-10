<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ParapharmacyApiController extends Controller
{
    /**
     * List all parapharmacies (public)
     */
    public function index(Request $request)
    {
        try {
            $hasGuard = Schema::hasColumn('parapharmacie_profiles', 'guard');
            $select = [
                'users.id',
                DB::raw('parapharmacie_profiles.id as parapharmacie_id'),
                'parapharmacie_profiles.nom_parapharmacie',
                DB::raw('parapharmacie_profiles.nom_parapharmacie as name'),
                'parapharmacie_profiles.adresse',
                'parapharmacie_profiles.ville',
                'parapharmacie_profiles.services',
                'parapharmacie_profiles.description',
                'parapharmacie_profiles.org_presentation',
                'parapharmacie_profiles.services_description',
                'parapharmacie_profiles.gerant_name',
                'parapharmacie_profiles.horaire_start',
                'parapharmacie_profiles.horaire_end',
                'parapharmacie_profiles.rating',
                'parapharmacie_profiles.etablissement_image',
                'parapharmacie_profiles.profile_image',
                'parapharmacie_profiles.gallery',
                'parapharmacie_profiles.imgs',
                $hasGuard ? 'parapharmacie_profiles.guard' : DB::raw('0 as guard'),
                'parapharmacie_profiles.disponible',
                'parapharmacie_profiles.vacation_mode',
                'parapharmacie_profiles.absence_start_date',
                'parapharmacie_profiles.absence_end_date',
                'parapharmacie_profiles.vacation_auto_reactivate_date',
                'users.email',
                'users.phone',
                'users.is_verified',
                'parapharmacie_profiles.created_at',
                'parapharmacie_profiles.updated_at'
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
                    'gerant_name' => $p->gerant_name,
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
        } catch (\Exception $e) {
            Log::error('Error fetching parapharmacies', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Error fetching parapharmacies'], 500);
        }
    }

    /**
     * Search parapharmacies by city (public)
     */
    public function searchByCity(Request $request)
    {
        try {
            $ville = $request->get('ville');
            $hasGuard = Schema::hasColumn('parapharmacie_profiles', 'guard');

            $select = [
                'users.id',
                DB::raw('parapharmacie_profiles.id as parapharmacie_id'),
                'parapharmacie_profiles.nom_parapharmacie',
                DB::raw('parapharmacie_profiles.nom_parapharmacie as name'),
                'parapharmacie_profiles.adresse',
                'parapharmacie_profiles.ville',
                'parapharmacie_profiles.services',
                'parapharmacie_profiles.description',
                'parapharmacie_profiles.org_presentation',
                'parapharmacie_profiles.services_description',
                'parapharmacie_profiles.responsable_name',
                'parapharmacie_profiles.horaire_start',
                'parapharmacie_profiles.horaire_end',
                'parapharmacie_profiles.rating',
                'parapharmacie_profiles.etablissement_image',
                'parapharmacie_profiles.profile_image',
                'parapharmacie_profiles.gallery',
                'parapharmacie_profiles.imgs',
                $hasGuard ? 'parapharmacie_profiles.guard' : DB::raw('0 as guard'),
                'parapharmacie_profiles.disponible',
                'parapharmacie_profiles.vacation_mode',
                'parapharmacie_profiles.absence_start_date',
                'parapharmacie_profiles.absence_end_date',
                'parapharmacie_profiles.vacation_auto_reactivate_date',
                'users.email',
                'users.phone',
                'users.is_verified',
                'parapharmacie_profiles.created_at',
                'parapharmacie_profiles.updated_at'
            ];

            $query = DB::table('parapharmacie_profiles')
                ->join('users', 'parapharmacie_profiles.user_id', '=', 'users.id')
                ->select($select);

            if ($ville && $ville !== 'Toutes les villes') {
                $query->where('parapharmacie_profiles.ville', 'LIKE', "%{$ville}%");
            }

            $rows = $query->get();

            $data = $rows->map(function ($p) {
                return [
                    'id' => $p->id,
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
        } catch (\Exception $e) {
            Log::error('Error searching parapharmacies by city', [
                'ville' => $ville ?? 'N/A',
                'error' => $e->getMessage(),
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
            $p = DB::table('parapharmacie_profiles')
                ->join('users', 'parapharmacie_profiles.user_id', '=', 'users.id')
                ->where('parapharmacie_profiles.user_id', $id)
                ->select(
                    'users.id',
                    'users.name',
                    DB::raw('parapharmacie_profiles.id as parapharmacie_id'),
                    'parapharmacie_profiles.nom_parapharmacie',
                    'parapharmacie_profiles.adresse',
                    'parapharmacie_profiles.ville',
                    'parapharmacie_profiles.services',
                    'parapharmacie_profiles.description',
                    'parapharmacie_profiles.org_presentation',
                    'parapharmacie_profiles.services_description',
                    'parapharmacie_profiles.responsable_name',
                    'parapharmacie_profiles.horaire_start',
                    'parapharmacie_profiles.horaire_end',
                    'parapharmacie_profiles.rating',
                    'parapharmacie_profiles.etablissement_image',
                    'parapharmacie_profiles.profile_image',
                    'parapharmacie_profiles.gallery',
                    'parapharmacie_profiles.imgs',
                    'parapharmacie_profiles.disponible',
                    'parapharmacie_profiles.vacation_mode',
                    'parapharmacie_profiles.vacation_auto_reactivate_date',
                    'parapharmacie_profiles.absence_start_date',
                    'parapharmacie_profiles.absence_end_date',
                    'parapharmacie_profiles.additional_info',
                    'users.email',
                    'users.phone',
                    'users.is_verified',
                    'parapharmacie_profiles.created_at',
                    'parapharmacie_profiles.updated_at'
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
                'moyens_paiement' => [],
                'moyens_transport' => [],
                'informations_pratiques' => null,
                'jours_disponibles' => [],
                'contact_urgence' => null,
                'etablissement_image' => $p->etablissement_image,
                'profile_image' => $p->profile_image,
                'gallery' => json_decode($p->gallery, true) ?: [],
                'imgs' => json_decode($p->imgs, true) ?: [],
                'disponible' => (bool) $p->disponible,
                'vacation_mode' => (bool) $p->vacation_mode,
                'vacation_auto_reactivate_date' => $p->vacation_auto_reactivate_date,
                'absence_start_date' => $p->absence_start_date,
                'absence_end_date' => $p->absence_end_date,
                'additional_info' => $p->additional_info,
                'type' => 'parapharmacie',
                'email' => $p->email,
                'phone' => $p->phone,
                'is_verified' => (bool) $p->is_verified,
                'created_at' => $p->created_at,
                'updated_at' => $p->updated_at,
            ];

            return response()->json($resp);
        } catch (\Exception $e) {
            Log::error('Error fetching parapharmacie profile', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error fetching parapharmacie profile'], 500);
        }
    }

    /**
     * Show parapharmacy by slug (optional helper)
     */
    public function showBySlug($slug)
    {
        try {
            $searchName = str_replace('-', ' ', $slug);
            $p = DB::table('parapharmacie_profiles')
                ->join('users', 'parapharmacie_profiles.user_id', '=', 'users.id')
                ->where(function ($q) use ($searchName) {
                    $q->whereRaw('LOWER(parapharmacie_profiles.nom_parapharmacie) LIKE ?', ['%' . strtolower($searchName) . '%'])
                      ->orWhereRaw('LOWER(users.name) LIKE ?', ['%' . strtolower($searchName) . '%']);
                })
                ->select(
                    'users.id', 'users.name',
                    DB::raw('parapharmacie_profiles.id as parapharmacie_id'),
                    'parapharmacie_profiles.nom_parapharmacie',
                    'parapharmacie_profiles.adresse',
                    'parapharmacie_profiles.ville',
                    'parapharmacie_profiles.services',
                    'parapharmacie_profiles.description',
                    'parapharmacie_profiles.org_presentation',
                    'parapharmacie_profiles.services_description',
                    'parapharmacie_profiles.responsable_name',
                    'parapharmacie_profiles.horaire_start',
                    'parapharmacie_profiles.horaire_end',
                    'parapharmacie_profiles.rating',
                    'parapharmacie_profiles.etablissement_image',
                    'parapharmacie_profiles.profile_image',
                    'parapharmacie_profiles.gallery',
                    'parapharmacie_profiles.imgs',
                    'parapharmacie_profiles.disponible',
                    'parapharmacie_profiles.vacation_mode',
                    'parapharmacie_profiles.vacation_auto_reactivate_date',
                    'parapharmacie_profiles.absence_start_date',
                    'parapharmacie_profiles.absence_end_date',
                    'users.email', 'users.phone', 'users.is_verified',
                    'parapharmacie_profiles.created_at', 'parapharmacie_profiles.updated_at'
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
        } catch (\Exception $e) {
            Log::error('Error fetching parapharmacie by slug', ['slug' => $slug, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error fetching parapharmacie profile'], 500);
        }
    }
}
