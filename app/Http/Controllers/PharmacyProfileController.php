<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class PharmacyProfileController extends Controller
{
    /**
     * Get authenticated pharmacy's profile data
     */
    public function profile(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Check if user is a pharmacy (role_id 7 for pharmacie)
            if ($user->role_id !== 7) {
                return response()->json(['error' => 'Access denied. User is not a pharmacy.'], 403);
            }

            // Get pharmacy profile data
            $pharmacy = \DB::table('pharmacie_profiles')
                ->join('users', 'pharmacie_profiles.user_id', '=', 'users.id')
                ->where('pharmacie_profiles.user_id', $user->id)
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.phone',
                    'users.is_verified',
                    'users.role_id',
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
                    'pharmacie_profiles.created_at',
                    'pharmacie_profiles.updated_at'
                )
                ->first();

            if (!$pharmacy) {
                return response()->json(['error' => 'Pharmacy profile not found'], 404);
            }

            // Format the response data
            $profileData = [
                'id' => $pharmacy->id,
                'pharmacy_id' => $pharmacy->pharmacy_id,
                'name' => $pharmacy->name,
                'email' => $pharmacy->email,
                'phone' => $pharmacy->phone,
                'is_verified' => (bool)$pharmacy->is_verified,
                'role_id' => $pharmacy->role_id,
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
                'created_at' => $pharmacy->created_at,
                'updated_at' => $pharmacy->updated_at
            ];

            return response()->json([
                'success' => true,
                'data' => $profileData
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching pharmacy profile:', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error fetching pharmacy profile',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update authenticated pharmacy's profile data
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Check if user is a pharmacy
            if ($user->role_id !== 7) {
                return response()->json(['error' => 'Access denied. User is not a pharmacy.'], 403);
            }

            // Validate request data
            $validated = $request->validate([
                'nom_pharmacie' => 'nullable|string|max:255',
                'adresse' => 'nullable|string|max:500',
                'ville' => 'nullable|string|max:100',
                'services' => 'nullable|array',
                'description' => 'nullable|string|max:2000',
                'org_presentation' => 'nullable|string|max:2000',
                'services_description' => 'nullable|string|max:2000',
                'responsable_name' => 'nullable|string|max:255',
                'horaire_start' => 'nullable|string|max:10',
                'horaire_end' => 'nullable|string|max:10',
                'guard' => 'nullable|boolean',
                'moyens_paiement' => 'nullable|array',
                'moyens_transport' => 'nullable|array',
                'informations_pratiques' => 'nullable|string|max:1000',
                'jours_disponibles' => 'nullable|array',
                'contact_urgence' => 'nullable|string|max:20',
                'additional_info' => 'nullable|string|max:1000',
                'disponible' => 'nullable|boolean',
                'vacation_mode' => 'nullable|boolean',
                'vacation_auto_reactivate_date' => 'nullable|date',
                'absence_start_date' => 'nullable|date',
                'absence_end_date' => 'nullable|date'
            ]);

            // Update pharmacy profile
            $updated = \DB::table('pharmacie_profiles')
                ->where('user_id', $user->id)
                ->update([
                    'nom_pharmacie' => $validated['nom_pharmacie'] ?? null,
                    'adresse' => $validated['adresse'] ?? null,
                    'ville' => $validated['ville'] ?? null,
                    'services' => isset($validated['services']) ? json_encode($validated['services'], JSON_UNESCAPED_UNICODE) : null,
                    'description' => $validated['description'] ?? null,
                    'org_presentation' => $validated['org_presentation'] ?? null,
                    'services_description' => $validated['services_description'] ?? null,
                    'responsable_name' => $validated['responsable_name'] ?? null,
                    'horaire_start' => $validated['horaire_start'] ?? null,
                    'horaire_end' => $validated['horaire_end'] ?? null,
                    'guard' => isset($validated['guard']) ? (bool)$validated['guard'] : null,
                    'moyens_paiement' => isset($validated['moyens_paiement']) ? json_encode($validated['moyens_paiement'], JSON_UNESCAPED_UNICODE) : null,
                    'moyens_transport' => isset($validated['moyens_transport']) ? json_encode($validated['moyens_transport'], JSON_UNESCAPED_UNICODE) : null,
                    'informations_pratiques' => $validated['informations_pratiques'] ?? null,
                    'jours_disponibles' => isset($validated['jours_disponibles']) ? json_encode($validated['jours_disponibles'], JSON_UNESCAPED_UNICODE) : null,
                    'contact_urgence' => $validated['contact_urgence'] ?? null,
                    'additional_info' => $validated['additional_info'] ?? null,
                    'disponible' => isset($validated['disponible']) ? (bool)$validated['disponible'] : null,
                    'vacation_mode' => isset($validated['vacation_mode']) ? (bool)$validated['vacation_mode'] : null,
                    'vacation_auto_reactivate_date' => $validated['vacation_auto_reactivate_date'] ?? null,
                    'absence_start_date' => $validated['absence_start_date'] ?? null,
                    'absence_end_date' => $validated['absence_end_date'] ?? null,
                    'updated_at' => now()
                ]);

            if (!$updated) {
                return response()->json(['error' => 'Failed to update pharmacy profile'], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Pharmacy profile updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating pharmacy profile:', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error updating pharmacy profile',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
