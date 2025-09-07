<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClinicController extends Controller
{
    /**
     * Get clinic profile by ID with all clinic-specific fields
     */
    public function show($id)
    {
        try {
            // Get clinic data from users table and clinique_profiles table
            $clinic = DB::table('users')
                ->leftJoin('clinique_profiles', 'users.id', '=', 'clinique_profiles.user_id')
                ->where('users.id', $id)
                ->whereIn('users.role_id', [6, 7, 8, 9, 10]) // All organization types
                ->select([
                    // User basic info
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.phone',
                    'users.role_id',
                    // Prefer clinic profile address fields (avoid non-existent users.ville/adresse)
                    'users.profile_image',
                    
                    // Clinic-specific fields from clinique_profiles table
                    'clinique_profiles.nom_clinique',
                    'clinique_profiles.adresse as clinic_adresse',
                    'clinique_profiles.ville as clinic_ville',
                    'clinique_profiles.services',
                    'clinique_profiles.description',
                    'clinique_profiles.profile_image as clinic_profile_image',
                    'clinique_profiles.etablissement_image',
                    'clinique_profiles.rating as clinic_rating',
                    'clinique_profiles.services_description',
                    'clinique_profiles.additional_info',
                    'clinique_profiles.vacation_mode',
                    'clinique_profiles.vacation_auto_reactivate_date',
                    'clinique_profiles.gallery',
                    'clinique_profiles.imgs',
                    'clinique_profiles.disponible as clinic_disponible',
                    'clinique_profiles.absence_start_date as clinic_absence_start',
                    'clinique_profiles.absence_end_date as clinic_absence_end',
                    'clinique_profiles.responsable_name',
                    'clinique_profiles.horaire_start',
                    'clinique_profiles.horaire_end',
                    'clinique_profiles.moyens_paiement',
                    'clinique_profiles.moyens_transport',
                    'clinique_profiles.informations_pratiques',
                    'clinique_profiles.jours_disponibles',
                    'clinique_profiles.contact_urgence',
                    'clinique_profiles.clinic_presentation',
                    'clinique_profiles.clinic_services_description'

                ])
                ->first();

            if (!$clinic) {
                return response()->json([
                    'success' => false,
                    'message' => 'Clinic not found'
                ], 404);
            }

            // Parse JSON fields if they exist
            if ($clinic->services && is_string($clinic->services)) {
                try {
                    $clinic->services = json_decode($clinic->services, true);
                } catch (\Exception $e) {
                    // Keep as string if JSON parsing fails
                }
            }

            if ($clinic->moyens_paiement && is_string($clinic->moyens_paiement)) {
                try {
                    $clinic->moyens_paiement = json_decode($clinic->moyens_paiement, true);
                } catch (\Exception $e) {
                    // Keep as string if JSON parsing fails
                }
            }

            if ($clinic->moyens_transport && is_string($clinic->moyens_transport)) {
                try {
                    $clinic->moyens_transport = json_decode($clinic->moyens_transport, true);
                } catch (\Exception $e) {
                    // Keep as string if JSON parsing fails
                }
            }

            if ($clinic->jours_disponibles && is_string($clinic->jours_disponibles)) {
                try {
                    $clinic->jours_disponibles = json_decode($clinic->jours_disponibles, true);
                } catch (\Exception $e) {
                    // Keep as string if JSON parsing fails
                }
            }

            if ($clinic->gallery && is_string($clinic->gallery)) {
                try {
                    $clinic->gallery = json_decode($clinic->gallery, true);
                } catch (\Exception $e) {
                    // Keep as string if JSON parsing fails
                }
            }

            // Parse imgs JSON if present
            if (isset($clinic->imgs) && $clinic->imgs && is_string($clinic->imgs)) {
                try {
                    $clinic->imgs = json_decode($clinic->imgs, true);
                } catch (\Exception $e) {
                    // Keep as string if JSON parsing fails
                }
            }

            return response()->json([
                'success' => true,
                'data' => $clinic
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching clinic profile: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching clinic profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all clinics with basic info
     */
    public function index()
    {
        try {
            $clinics = DB::table('users')
                ->leftJoin('clinique_profiles', 'users.id', '=', 'clinique_profiles.user_id')
                ->where('users.role_id', 6) // Only clinics
                ->select([
                    'users.id',
                    'users.name',
                    // Use clinic profile address fields (users.ville/adresse may not exist)
                    'clinique_profiles.ville as clinic_ville',
                    'clinique_profiles.adresse as clinic_adresse',
                    'clinique_profiles.rating as clinic_rating',
                    'clinique_profiles.disponible as clinic_disponible',
                    'clinique_profiles.nom_clinique',
                    'clinique_profiles.services',
                    'clinique_profiles.description',
                    'clinique_profiles.clinic_presentation',
                    'clinique_profiles.clinic_services_description'
                ])
                ->get();

            // Parse services JSON for each clinic
            foreach ($clinics as $clinic) {
                if ($clinic->services && is_string($clinic->services)) {
                    try {
                        $clinic->services = json_decode($clinic->services, true);
                    } catch (\Exception $e) {
                        // Keep as string if JSON parsing fails
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $clinics
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching clinics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error fetching clinics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search clinics by city
     */
    public function searchByCity(Request $request)
    {
        try {
            $city = $request->get('ville');
            
            $query = DB::table('users')
                ->leftJoin('clinique_profiles', 'users.id', '=', 'clinique_profiles.user_id')
                ->where('users.role_id', 6); // Only clinics

            if ($city && $city !== 'Toutes les villes') {
                // Filter by clinic profile city
                $query->where('clinique_profiles.ville', 'LIKE', '%' . $city . '%');
            }

            $clinics = $query->select([
                'users.id',
                'users.name',
                // Use clinic profile address fields (users.ville/adresse may not exist)
                'clinique_profiles.ville as clinic_ville',
                'clinique_profiles.adresse as clinic_adresse',
                'clinique_profiles.rating as clinic_rating',
                'clinique_profiles.disponible as clinic_disponible',
                'clinique_profiles.nom_clinique',
                'clinique_profiles.services',
                'clinique_profiles.description',
                'clinique_profiles.clinic_presentation',
                'clinique_profiles.clinic_services_description'
            ])->get();

            // Parse services JSON for each clinic
            foreach ($clinics as $clinic) {
                if ($clinic->services && is_string($clinic->services)) {
                    try {
                        $clinic->services = json_decode($clinic->services, true);
                    } catch (\Exception $e) {
                        // Keep as string if JSON parsing fails
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $clinics
            ]);

        } catch (\Exception $e) {
            Log::error('Error searching clinics: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error searching clinics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
