<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParapharmacieProfile;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PharmacyApiController extends Controller
{
    /**
     * Get all pharmacies from parapharmacie_profiles table
     */
    public function index(Request $request)
    {
        try {
            // Handle older schemas gracefully (guard columns may not exist yet)
            $hasGuard = Schema::hasColumn('pharmacie_profiles', 'guard');
            $hasGuardStart = Schema::hasColumn('pharmacie_profiles', 'guard_start_date');
            $hasGuardEnd = Schema::hasColumn('pharmacie_profiles', 'guard_end_date');

            $select = [
                'users.id',
                \DB::raw('pharmacie_profiles.id as pharmacy_id'),
                'pharmacie_profiles.nom_pharmacie',
                \DB::raw('pharmacie_profiles.nom_pharmacie as name'),
                'pharmacie_profiles.adresse',
                'pharmacie_profiles.ville',
                'pharmacie_profiles.services',
                'pharmacie_profiles.description',
                'pharmacie_profiles.org_presentation',
                'pharmacie_profiles.services_description',
                'pharmacie_profiles.responsable_name',
                'pharmacie_profiles.horaire_start',
                'pharmacie_profiles.horaire_end',
                'pharmacie_profiles.rating',
                'pharmacie_profiles.etablissement_image',
                'pharmacie_profiles.profile_image',
                'pharmacie_profiles.gallery',
                'pharmacie_profiles.imgs',
                $hasGuard ? 'pharmacie_profiles.guard' : \DB::raw('0 as guard'),
                $hasGuardStart ? 'pharmacie_profiles.guard_start_date' : \DB::raw('NULL as guard_start_date'),
                $hasGuardEnd ? 'pharmacie_profiles.guard_end_date' : \DB::raw('NULL as guard_end_date'),
                'pharmacie_profiles.disponible',
                'pharmacie_profiles.vacation_mode',
                'pharmacie_profiles.absence_start_date',
                'pharmacie_profiles.absence_end_date',
                'pharmacie_profiles.vacation_auto_reactivate_date',
                'users.email',
                'users.phone',
                'users.is_verified',
                'pharmacie_profiles.created_at',
                'pharmacie_profiles.updated_at'
            ];

            // Get pharmacies from pharmacie_profiles table
            $pharmacies = \DB::table('pharmacie_profiles')
                ->join('users', 'pharmacie_profiles.user_id', '=', 'users.id')
                ->select($select)
                ->get();

            $pharmacyData = $pharmacies->map(function($pharmacy) {
                // Compute effective guard (false if guard_end_date passed)
                $effectiveGuard = (bool)$pharmacy->guard;
                try {
                    if ($pharmacy->guard_end_date) {
                        $effectiveGuard = (strtotime($pharmacy->guard_end_date) >= strtotime(date('Y-m-d'))) && $effectiveGuard;
                    }
                } catch (\Exception $e) {}

                return [
                    'id' => $pharmacy->id,
                    'pharmacy_id' => $pharmacy->pharmacy_id,
                    'nom_pharmacie' => $pharmacy->nom_pharmacie,
                    'name' => $pharmacy->name,
                    'adresse' => $pharmacy->adresse,
                    'ville' => $pharmacy->ville,
                    'services' => json_decode($pharmacy->services, true) ?: [],
                    'description' => $pharmacy->description,
                    'org_presentation' => $pharmacy->org_presentation,
                    'services_description' => $pharmacy->services_description,
                    'responsable_name' => $pharmacy->responsable_name,
                    'horaire_start' => $pharmacy->horaire_start,
                    'horaire_end' => $pharmacy->horaire_end,
                    'rating' => (string)$pharmacy->rating,
                    'etablissement_image' => $pharmacy->etablissement_image,
                    'profile_image' => $pharmacy->profile_image,
                    'gallery' => json_decode($pharmacy->gallery, true) ?: [],
                    'imgs' => json_decode($pharmacy->imgs, true) ?: [],
                    'guard' => (bool)$effectiveGuard,
                    'guard_start_date' => $pharmacy->guard_start_date,
                    'guard_end_date' => $pharmacy->guard_end_date,
                    'disponible' => (bool)$pharmacy->disponible,
                    'vacation_mode' => (bool)$pharmacy->vacation_mode,
                    'absence_start_date' => $pharmacy->absence_start_date,
                    'absence_end_date' => $pharmacy->absence_end_date,
                    'vacation_auto_reactivate_date' => $pharmacy->vacation_auto_reactivate_date,
                    'type' => 'pharmacie',
                    'email' => $pharmacy->email,
                    'phone' => $pharmacy->phone,
                    'is_verified' => (bool)$pharmacy->is_verified,
                    'created_at' => $pharmacy->created_at,
                    'updated_at' => $pharmacy->updated_at
                ];
            })->toArray();

            return response()->json($pharmacyData);

        } catch (\Exception $e) {
            Log::error('Error fetching pharmacies:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'error' => 'Error fetching pharmacies',
                'message' => $e->getMessage(),
                'debug' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Search pharmacies by city
     */
    public function searchByCity(Request $request)
    {
        try {
            $ville = $request->get('ville');
            
            // Get pharmacies from pharmacie_profiles table with city filter
            $hasGuard = Schema::hasColumn('pharmacie_profiles', 'guard');
            $hasGuardStart = Schema::hasColumn('pharmacie_profiles', 'guard_start_date');
            $hasGuardEnd = Schema::hasColumn('pharmacie_profiles', 'guard_end_date');

            $select = [
                'users.id',
                \DB::raw('pharmacie_profiles.id as pharmacy_id'),
                'pharmacie_profiles.nom_pharmacie',
                'pharmacie_profiles.adresse',
                'pharmacie_profiles.ville',
                'pharmacie_profiles.services',
                'pharmacie_profiles.description',
                'pharmacie_profiles.org_presentation',
                'pharmacie_profiles.services_description',
                'pharmacie_profiles.responsable_name',
                'pharmacie_profiles.horaire_start',
                'pharmacie_profiles.horaire_end',
                'pharmacie_profiles.rating',
                'pharmacie_profiles.etablissement_image',
                'pharmacie_profiles.profile_image',
                'pharmacie_profiles.gallery',
                'pharmacie_profiles.imgs',
                $hasGuard ? 'pharmacie_profiles.guard' : \DB::raw('0 as guard'),
                $hasGuardStart ? 'pharmacie_profiles.guard_start_date' : \DB::raw('NULL as guard_start_date'),
                $hasGuardEnd ? 'pharmacie_profiles.guard_end_date' : \DB::raw('NULL as guard_end_date'),
                'pharmacie_profiles.disponible',
                'pharmacie_profiles.vacation_mode',
                'pharmacie_profiles.absence_start_date',
                'pharmacie_profiles.absence_end_date',
                'pharmacie_profiles.vacation_auto_reactivate_date',
                'pharmacie_profiles.moyens_paiement',
                'pharmacie_profiles.moyens_transport',
                'pharmacie_profiles.informations_pratiques',
                'pharmacie_profiles.jours_disponibles',
                'pharmacie_profiles.contact_urgence',
                'users.email',
                'users.phone',
                'users.is_verified',
                'pharmacie_profiles.created_at',
                'pharmacie_profiles.updated_at'
            ];

            $query = \DB::table('pharmacie_profiles')
                ->join('users', 'pharmacie_profiles.user_id', '=', 'users.id')
                ->select($select);
            
            if ($ville && $ville !== 'Toutes les villes') {
                $query->where('pharmacie_profiles.ville', 'LIKE', "%{$ville}%");
            }
            
            $pharmacies = $query->get();
            
            $pharmacyData = $pharmacies->map(function($pharmacy) {
                $effectiveGuard = (bool)$pharmacy->guard;
                try {
                    if ($pharmacy->guard_end_date) {
                        $effectiveGuard = (strtotime($pharmacy->guard_end_date) >= strtotime(date('Y-m-d'))) && $effectiveGuard;
                    }
                } catch (\Exception $e) {}

                return [
                    'id' => $pharmacy->id,
                    'pharmacy_id' => $pharmacy->pharmacy_id,
                    'nom_pharmacie' => $pharmacy->nom_pharmacie,
                    'name' => $pharmacy->nom_pharmacie,
                    'adresse' => $pharmacy->adresse,
                    'ville' => $pharmacy->ville,
                    'services' => json_decode($pharmacy->services, true) ?: [],
                    'description' => $pharmacy->description,
                    'org_presentation' => $pharmacy->org_presentation,
                    'services_description' => $pharmacy->services_description,
                    'responsable_name' => $pharmacy->responsable_name,
                    'horaire_start' => $pharmacy->horaire_start,
                    'horaire_end' => $pharmacy->horaire_end,
                    'rating' => (string)$pharmacy->rating,
                    'etablissement_image' => $pharmacy->etablissement_image,
                    'profile_image' => $pharmacy->profile_image,
                    'gallery' => json_decode($pharmacy->gallery, true) ?: [],
                    'imgs' => json_decode($pharmacy->imgs, true) ?: [],
                    'guard' => (bool)$effectiveGuard,
                    'guard_start_date' => $pharmacy->guard_start_date,
                    'guard_end_date' => $pharmacy->guard_end_date,
                    'disponible' => (bool)$pharmacy->disponible,
                    'vacation_mode' => (bool)$pharmacy->vacation_mode,
                    'absence_start_date' => $pharmacy->absence_start_date,
                    'absence_end_date' => $pharmacy->absence_end_date,
                    'vacation_auto_reactivate_date' => $pharmacy->vacation_auto_reactivate_date,
                    'moyens_paiement' => json_decode($pharmacy->moyens_paiement, true) ?: [],
                    'moyens_transport' => json_decode($pharmacy->moyens_transport, true) ?: [],
                    'informations_pratiques' => $pharmacy->informations_pratiques,
                    'jours_disponibles' => json_decode($pharmacy->jours_disponibles, true) ?: [],
                    'contact_urgence' => $pharmacy->contact_urgence,
                    'type' => 'pharmacie',
                    'email' => $pharmacy->email,
                    'phone' => $pharmacy->phone,
                    'is_verified' => (bool)$pharmacy->is_verified,
                    'created_at' => $pharmacy->created_at,
                    'updated_at' => $pharmacy->updated_at
                ];
            })->toArray();

            return response()->json($pharmacyData);

        } catch (\Exception $e) {
            Log::error('Error searching pharmacies by city:', [
                'ville' => $ville ?? 'N/A',
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'error' => 'Error searching pharmacies'
            ], 500);
        }
    }

    /**
     * Get single pharmacy by user ID for public profile display
     */
    public function show($id)
    {
        try {
            // Find pharmacy by user_id from pharmacie_profiles table
            $pharmacy = \DB::table('pharmacie_profiles')
                ->join('users', 'pharmacie_profiles.user_id', '=', 'users.id')
                ->where('pharmacie_profiles.user_id', $id)
                ->select(
                    'users.id',
                    'users.name',
                    'pharmacie_profiles.id as pharmacy_id',
                    'pharmacie_profiles.nom_pharmacie',
                    'pharmacie_profiles.adresse',
                    'pharmacie_profiles.ville',
                    'pharmacie_profiles.services',
                    'pharmacie_profiles.description',
                    'pharmacie_profiles.org_presentation',
                    'pharmacie_profiles.services_description',
                    'pharmacie_profiles.responsable_name',
                    'pharmacie_profiles.horaire_start',
                    'pharmacie_profiles.horaire_end',
                    'pharmacie_profiles.rating',
                    'pharmacie_profiles.guard',
                    'pharmacie_profiles.guard_start_date',
                    'pharmacie_profiles.guard_end_date',
                    'pharmacie_profiles.moyens_paiement',
                    'pharmacie_profiles.moyens_transport',
                    'pharmacie_profiles.informations_pratiques',
                    'pharmacie_profiles.jours_disponibles',
                    'pharmacie_profiles.contact_urgence',
                    'pharmacie_profiles.etablissement_image',
                    'pharmacie_profiles.profile_image',
                    'pharmacie_profiles.gallery',
                    'pharmacie_profiles.imgs',
                    'pharmacie_profiles.disponible',
                    'pharmacie_profiles.vacation_mode',
                    'pharmacie_profiles.vacation_auto_reactivate_date',
                    'pharmacie_profiles.absence_start_date',
                    'pharmacie_profiles.absence_end_date',
                    'pharmacie_profiles.additional_info',
                    'users.email',
                    'users.phone',
                    'users.is_verified',
                    'pharmacie_profiles.created_at',
                    'pharmacie_profiles.updated_at'
                )
                ->first();

            if (!$pharmacy) {
                return response()->json(['message' => 'Pharmacy not found'], 404);
            }
            
            $effectiveGuard = (bool)$pharmacy->guard;
            try {
                if ($pharmacy->guard_end_date) {
                    $effectiveGuard = (strtotime($pharmacy->guard_end_date) >= strtotime(date('Y-m-d'))) && $effectiveGuard;
                }
            } catch (\Exception $e) {}

            $pharmacyData = [
                'id' => $pharmacy->id,
                'pharmacy_id' => $pharmacy->pharmacy_id,
                'name' => $pharmacy->name,
                'nom_pharmacie' => $pharmacy->nom_pharmacie,
                'adresse' => $pharmacy->adresse,
                'ville' => $pharmacy->ville,
                'services' => json_decode($pharmacy->services, true) ?: [],
                'description' => $pharmacy->description,
                'org_presentation' => $pharmacy->org_presentation,
                'services_description' => $pharmacy->services_description,
                'responsable_name' => $pharmacy->responsable_name,
                'horaire_start' => $pharmacy->horaire_start,
                'horaire_end' => $pharmacy->horaire_end,
                'rating' => (float)$pharmacy->rating,
                'guard' => (bool)$effectiveGuard,
                'guard_start_date' => $pharmacy->guard_start_date,
                'guard_end_date' => $pharmacy->guard_end_date,
                'moyens_paiement' => json_decode($pharmacy->moyens_paiement, true) ?: [],
                'moyens_transport' => json_decode($pharmacy->moyens_transport, true) ?: [],
                'informations_pratiques' => $pharmacy->informations_pratiques,
                'jours_disponibles' => json_decode($pharmacy->jours_disponibles, true) ?: [],
                'contact_urgence' => $pharmacy->contact_urgence,
                'etablissement_image' => $pharmacy->etablissement_image,
                'profile_image' => $pharmacy->profile_image,
                'gallery' => json_decode($pharmacy->gallery, true) ?: [],
                'imgs' => json_decode($pharmacy->imgs, true) ?: [],
                'disponible' => (bool)$pharmacy->disponible,
                'vacation_mode' => (bool)$pharmacy->vacation_mode,
                'vacation_auto_reactivate_date' => $pharmacy->vacation_auto_reactivate_date,
                'absence_start_date' => $pharmacy->absence_start_date,
                'absence_end_date' => $pharmacy->absence_end_date,
                'additional_info' => $pharmacy->additional_info,
                'type' => 'pharmacie',
                'email' => $pharmacy->email,
                'phone' => $pharmacy->phone,
                'is_verified' => (bool)$pharmacy->is_verified,
                'created_at' => $pharmacy->created_at,
                'updated_at' => $pharmacy->updated_at
            ];

            return response()->json($pharmacyData);

        } catch (\Exception $e) {
            Log::error('Error fetching pharmacy profile:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return response()->json(['error' => 'Error fetching pharmacy profile'], 500);
        }
    }

    /**
     * Get pharmacy by name slug for public profile display
     */
    public function showBySlug($slug)
    {
        try {
            // Convert slug back to name (replace hyphens with spaces and handle case)
            $searchName = str_replace('-', ' ', $slug);
            
            // Find pharmacy by name from pharmacie_profiles table
            $pharmacy = \DB::table('pharmacie_profiles')
                ->join('users', 'pharmacie_profiles.user_id', '=', 'users.id')
                ->where(function($query) use ($searchName) {
                    $query->whereRaw('LOWER(pharmacie_profiles.nom_pharmacie) LIKE ?', ['%' . strtolower($searchName) . '%'])
                          ->orWhereRaw('LOWER(users.name) LIKE ?', ['%' . strtolower($searchName) . '%']);
                })
                ->select(
                    'users.id',
                    'users.name',
                    'pharmacie_profiles.id as pharmacy_id',
                    'pharmacie_profiles.nom_pharmacie',
                    'pharmacie_profiles.adresse',
                    'pharmacie_profiles.ville',
                    'pharmacie_profiles.services',
                    'pharmacie_profiles.description',
                    'pharmacie_profiles.org_presentation',
                    'pharmacie_profiles.services_description',
                    'pharmacie_profiles.responsable_name',
                    'pharmacie_profiles.horaire_start',
                    'pharmacie_profiles.horaire_end',
                    'pharmacie_profiles.rating',
                    'pharmacie_profiles.guard',
                    'pharmacie_profiles.moyens_paiement',
                    'pharmacie_profiles.moyens_transport',
                    'pharmacie_profiles.informations_pratiques',
                    'pharmacie_profiles.jours_disponibles',
                    'pharmacie_profiles.contact_urgence',
                    'pharmacie_profiles.etablissement_image',
                    'pharmacie_profiles.profile_image',
                    'pharmacie_profiles.gallery',
                    'pharmacie_profiles.disponible',
                    'pharmacie_profiles.vacation_mode',
                    'pharmacie_profiles.vacation_auto_reactivate_date',
                    'pharmacie_profiles.absence_start_date',
                    'pharmacie_profiles.absence_end_date',
                    'pharmacie_profiles.additional_info',
                    'users.email',
                    'users.phone',
                    'users.is_verified',
                    'pharmacie_profiles.created_at',
                    'pharmacie_profiles.updated_at'
                )
                ->first();

            if (!$pharmacy) {
                return response()->json(['message' => 'Pharmacy not found'], 404);
            }
            
            $pharmacyData = [
                'id' => $pharmacy->id,
                'pharmacy_id' => $pharmacy->pharmacy_id,
                'name' => $pharmacy->name,
                'nom_pharmacie' => $pharmacy->nom_pharmacie,
                'adresse' => $pharmacy->adresse,
                'ville' => $pharmacy->ville,
                'services' => json_decode($pharmacy->services, true) ?: [],
                'description' => $pharmacy->description,
                'org_presentation' => $pharmacy->org_presentation,
                'services_description' => $pharmacy->services_description,
                'responsable_name' => $pharmacy->responsable_name,
                'horaire_start' => $pharmacy->horaire_start,
                'horaire_end' => $pharmacy->horaire_end,
                'rating' => (float)$pharmacy->rating,
                'guard' => (bool)$pharmacy->guard,
                'moyens_paiement' => json_decode($pharmacy->moyens_paiement, true) ?: [],
                'moyens_transport' => json_decode($pharmacy->moyens_transport, true) ?: [],
                'informations_pratiques' => $pharmacy->informations_pratiques,
                'jours_disponibles' => json_decode($pharmacy->jours_disponibles, true) ?: [],
                'contact_urgence' => $pharmacy->contact_urgence,
                'etablissement_image' => $pharmacy->etablissement_image,
                'profile_image' => $pharmacy->profile_image,
                'gallery' => json_decode($pharmacy->gallery, true) ?: [],
                'disponible' => (bool)$pharmacy->disponible,
                'vacation_mode' => (bool)$pharmacy->vacation_mode,
                'vacation_auto_reactivate_date' => $pharmacy->vacation_auto_reactivate_date,
                'absence_start_date' => $pharmacy->absence_start_date,
                'absence_end_date' => $pharmacy->absence_end_date,
                'additional_info' => $pharmacy->additional_info,
                'type' => 'pharmacie',
                'email' => $pharmacy->email,
                'phone' => $pharmacy->phone,
                'is_verified' => (bool)$pharmacy->is_verified,
                'created_at' => $pharmacy->created_at,
                'updated_at' => $pharmacy->updated_at
            ];

            return response()->json($pharmacyData);

        } catch (\Exception $e) {
            Log::error('Error fetching pharmacy profile by slug:', [
                'slug' => $slug,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return response()->json(['error' => 'Error fetching pharmacy profile'], 500);
        }
    }
}
