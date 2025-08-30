<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Get any user profile by ID - auto-detects type and returns appropriate data
     */
    public function show($id)
    {
        Log::info("ProfileController::show called with ID: $id");
        
        // Get the user and their role
        $user = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $id)
            ->select('users.*', 'roles.name as role_name')
            ->first();

        if (!$user) {
            Log::error("User not found for ID: $id");
            return response()->json(['message' => 'Profile not found'], 404);
        }

        Log::info("User found: " . json_encode(['id' => $user->id, 'name' => $user->name, 'role' => $user->role_name]));

        // Define profile types
        $doctorTypes = ['medecin'];
        $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
        $professionalTypes = ['kine', 'orthophoniste', 'psychologue'];
        
        // Skip admin and patient roles
        if (in_array($user->role_name, ['admin', 'patient'])) {
            return response()->json(['message' => 'Profile not accessible'], 403);
        }

        $profile = null;
        $profileData = [];

        // Handle doctors
        if (in_array($user->role_name, $doctorTypes)) {
            $profile = DB::table('medecin_profiles')->where('user_id', $id)->first();
            if ($profile) {
                $profileData = $this->formatDoctorProfile($user, $profile);
            }
        }
        // Handle organizations
        elseif (in_array($user->role_name, $orgTypes)) {
            $tableName = $user->role_name . '_profiles';
            Log::info("Looking for profile in table: $tableName for user_id: $id");
            $profile = DB::table($tableName)->where('user_id', $id)->first();
            Log::info("Profile found: " . ($profile ? 'YES' : 'NO'));
            if ($profile) {
                Log::info("Profile data: " . json_encode($profile));
                $profileData = $this->formatOrganizationProfile($user, $profile);
            } else {
                Log::warning("No profile found in $tableName for user_id $id");
            }
        }
        // Handle other professionals
        elseif (in_array($user->role_name, $professionalTypes)) {
            $tableName = $user->role_name . '_profiles';
            $profile = DB::table($tableName)->where('user_id', $id)->first();
            if ($profile) {
                $profileData = $this->formatProfessionalProfile($user, $profile);
            }
        }

        // If no profile found, return basic user data
        if (!$profile) {
            Log::warning("No profile found for user $id with role {$user->role_name}");
            // Check if profile table exists and show available profiles
            if (in_array($user->role_name, $orgTypes)) {
                $tableName = $user->role_name . '_profiles';
                $allProfiles = DB::table($tableName)->select('user_id')->get();
                Log::info("Available user_ids in $tableName: " . json_encode($allProfiles->pluck('user_id')));
            }
            $profileData = $this->formatBasicProfile($user);
        }

        Log::info("Returning profile data for user $id");
        return response()->json($profileData);
    }

    private function formatDoctorProfile($user, $profile)
    {
        // Parse arrays
        $specialties = [];
        if ($profile->specialty) {
            $specialties = json_decode($profile->specialty, true) ?: [$profile->specialty];
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
            'profile_image' => $profile->profile_image ?? null,
            'horaire_start' => $profile->horaire_start ?? null,
            'horaire_end' => $profile->horaire_end ?? null,
            'moyens_paiement' => isset($profile->moyens_paiement) ? json_decode($profile->moyens_paiement, true) : [],
            'moyens_transport' => isset($profile->moyens_transport) ? json_decode($profile->moyens_transport, true) : [],
            'jours_disponibles' => isset($profile->jours_disponibles) ? json_decode($profile->jours_disponibles, true) : [],
            'diplomes' => isset($profile->diplomes) ? json_decode($profile->diplomes, true) : [],
            'experiences' => isset($profile->experiences) ? json_decode($profile->experiences, true) : [],
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

        // Extract gallery images
        $galleryImages = [];
        if (isset($profile->gallery) && $profile->gallery) {
            $gallery = json_decode($profile->gallery, true);
            if (is_array($gallery)) {
                $galleryImages = $gallery;
            }
        } elseif (isset($profile->etablissement_image) && $profile->etablissement_image) {
            $galleryImages = [$profile->etablissement_image];
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
            'etablissement_image' => $profile->etablissement_image ?? null,
            'profile_image' => $profile->profile_image ?? null,
            'horaires' => $horaires,
            'horaire_start' => $horaireStart,
            'horaire_end' => $horaireEnd,
            'services' => $services,
            'moyens_paiement' => isset($profile->moyens_paiement) ? json_decode($profile->moyens_paiement, true) : [],
            'moyens_transport' => isset($profile->moyens_transport) ? json_decode($profile->moyens_transport, true) : [],
            'jours_disponibles' => isset($profile->jours_disponibles) ? json_decode($profile->jours_disponibles, true) : [],
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
        // Parse arrays
        $specialties = [];
        if ($profile->specialty) {
            $specialties = json_decode($profile->specialty, true) ?: [$profile->specialty];
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
            'profile_image' => $profile->profile_image ?? null,
            'horaire_start' => $profile->horaire_start ?? null,
            'horaire_end' => $profile->horaire_end ?? null,
            'moyens_paiement' => isset($profile->moyens_paiement) ? json_decode($profile->moyens_paiement, true) : [],
            'moyens_transport' => isset($profile->moyens_transport) ? json_decode($profile->moyens_transport, true) : [],
            'jours_disponibles' => isset($profile->jours_disponibles) ? json_decode($profile->jours_disponibles, true) : [],
            'diplomes' => isset($profile->diplomes) ? json_decode($profile->diplomes, true) : [],
            'experiences' => isset($profile->experiences) ? json_decode($profile->experiences, true) : [],
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
}
