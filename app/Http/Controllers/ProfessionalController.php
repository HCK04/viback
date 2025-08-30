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
        // Parse JSON fields
        $specialties = [];
        if (isset($profile->specialty) && $profile->specialty) {
            $decoded = json_decode($profile->specialty, true);
            $specialties = is_array($decoded) ? $decoded : [$profile->specialty];
        }

        $diplomes = [];
        if (isset($profile->diplomes) && $profile->diplomes) {
            $decoded = json_decode($profile->diplomes, true);
            $diplomes = is_array($decoded) ? $decoded : [];
        }

        $experiences = [];
        if (isset($profile->experiences) && $profile->experiences) {
            $decoded = json_decode($profile->experiences, true);
            $experiences = is_array($decoded) ? $decoded : [];
        }

        $moyensPaiement = [];
        if (isset($profile->moyens_paiement) && $profile->moyens_paiement) {
            $decoded = json_decode($profile->moyens_paiement, true);
            $moyensPaiement = is_array($decoded) ? $decoded : [];
        }

        $moyensTransport = [];
        if (isset($profile->moyens_transport) && $profile->moyens_transport) {
            $decoded = json_decode($profile->moyens_transport, true);
            $moyensTransport = is_array($decoded) ? $decoded : [];
        }

        $joursDisponibles = [];
        if (isset($profile->jours_disponibles) && $profile->jours_disponibles) {
            $decoded = json_decode($profile->jours_disponibles, true);
            $joursDisponibles = is_array($decoded) ? $decoded : [];
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

        $horaires = [];
        if (isset($profile->horaires) && $profile->horaires) {
            $decoded = json_decode($profile->horaires, true);
            if (is_array($decoded)) {
                $horaires = $decoded;
            }
        }

        $moyensPaiement = [];
        if (isset($profile->moyens_paiement) && $profile->moyens_paiement) {
            $decoded = json_decode($profile->moyens_paiement, true);
            $moyensPaiement = is_array($decoded) ? $decoded : [];
        }

        $moyensTransport = [];
        if (isset($profile->moyens_transport) && $profile->moyens_transport) {
            $decoded = json_decode($profile->moyens_transport, true);
            $moyensTransport = is_array($decoded) ? $decoded : [];
        }

        $joursDisponibles = [];
        if (isset($profile->jours_disponibles) && $profile->jours_disponibles) {
            $decoded = json_decode($profile->jours_disponibles, true);
            $joursDisponibles = is_array($decoded) ? $decoded : [];
        }

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
}
