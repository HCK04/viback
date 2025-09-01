<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class RendezVousController extends Controller
{
    /**
     * Get complete professional profile data for rendezvous page
     */
    public function getProfessionalData($id)
    {
        Log::info("RendezVousController: Fetching professional data for user ID: $id");
        
        // Get user with role information
        $user = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $id)
            ->select('users.*', 'roles.name as role_name')
            ->first();

        if (!$user) {
            Log::error("User not found with ID: $id");
            return response()->json(['message' => 'User not found'], 404);
        }

        Log::info("Found user: {$user->name} with role: {$user->role_name}");

        // Define professional and organization types
        $professionalTypes = ['medecin', 'kine', 'orthophoniste', 'psychologue'];
        $organizationTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];

        $profileData = null;

        // Handle individual professionals
        if (in_array($user->role_name, $professionalTypes)) {
            $tableName = $user->role_name . '_profiles';
            Log::info("Looking for professional profile in table: $tableName");
            
            $profile = DB::table($tableName)->where('user_id', $id)->first();
            
            if ($profile) {
                Log::info("Found professional profile");
                $profileData = $this->formatProfessionalData($user, $profile);
            } else {
                Log::warning("No professional profile found in $tableName for user_id $id");
            }
        }
        // Handle organizations
        elseif (in_array($user->role_name, $organizationTypes)) {
            $tableName = $user->role_name . '_profiles';
            Log::info("Looking for organization profile in table: $tableName");
            
            $profile = DB::table($tableName)->where('user_id', $id)->first();
            
            if ($profile) {
                Log::info("Found organization profile");
                $profileData = $this->formatOrganizationData($user, $profile);
            } else {
                Log::warning("No organization profile found in $tableName for user_id $id");
            }
        }

        // If no profile found, return basic user data
        if (!$profile) {
            Log::warning("No profile found for user $id with role {$user->role_name}");
            $profileData = $this->formatBasicData($user);
        }

        Log::info("Returning rendezvous profile data for user $id");
        return response()->json($profileData);
    }

    private function formatProfessionalData($user, $profile)
    {
        Log::info("=== FORMATTING PROFESSIONAL DATA FOR RENDEZVOUS ===");
        Log::info("Raw profile data: " . json_encode($profile));
        
        // Parse JSON fields with proper error handling
        $joursDisponibles = $this->parseJsonField($profile->jours_disponibles ?? null, 'jours_disponibles');
        $moyensTransport = $this->parseJsonField($profile->moyens_transport ?? null, 'moyens_transport');
        $moyensPaiement = $this->parseJsonField($profile->moyens_paiement ?? null, 'moyens_paiement');
        $specialties = $this->parseJsonField($profile->specialty ?? null, 'specialty');
        $diplomes = $this->parseJsonField($profile->diplomes ?? null, 'diplomes');
        $experiences = $this->parseJsonField($profile->experiences ?? null, 'experiences');

        Log::info("Parsed jours_disponibles: " . json_encode($joursDisponibles));
        Log::info("Parsed moyens_transport: " . json_encode($moyensTransport));
        Log::info("Parsed moyens_paiement: " . json_encode($moyensPaiement));

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
            'absence_start_date' => $profile->absence_start_date ?? null,
            'absence_end_date' => $profile->absence_end_date ?? null,
            'rdv_patients_suivis_uniquement' => $profile->rdv_patients_suivis_uniquement ?? false,
            'is_verified' => $user->is_verified ?? false,
            'created_at' => $user->created_at
        ];
    }

    private function formatOrganizationData($user, $profile)
    {
        Log::info("=== FORMATTING ORGANIZATION DATA FOR RENDEZVOUS ===");
        Log::info("Raw profile data: " . json_encode($profile));
        
        // Parse JSON fields
        $joursDisponibles = $this->parseJsonField($profile->jours_disponibles ?? null, 'jours_disponibles');
        $moyensTransport = $this->parseJsonField($profile->moyens_transport ?? null, 'moyens_transport');
        $moyensPaiement = $this->parseJsonField($profile->moyens_paiement ?? null, 'moyens_paiement');
        $services = $this->parseJsonField($profile->services ?? null, 'services');

        // Get organization name based on type
        $orgName = $user->name;
        if (isset($profile->nom_clinique)) $orgName = $profile->nom_clinique;
        elseif (isset($profile->nom_pharmacie)) $orgName = $profile->nom_pharmacie;
        elseif (isset($profile->nom_parapharmacie)) $orgName = $profile->nom_parapharmacie;
        elseif (isset($profile->nom_centre)) $orgName = $profile->nom_centre;
        elseif (isset($profile->nom_labo)) $orgName = $profile->nom_labo;

        return [
            'id' => $user->id,
            'name' => $orgName,
            'type' => $user->role_name,
            'role' => $user->role_name,
            'role_name' => $user->role_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'services' => $services,
            'adresse' => $profile->adresse ?? null,
            'location' => $profile->adresse ?? null,
            'ville' => $profile->ville ?? null,
            'presentation' => $profile->org_presentation ?? $profile->description ?? null,
            'informations_pratiques' => $profile->informations_pratiques ?? null,
            'profile_image' => $profile->etablissement_image ?? null,
            'horaire_start' => $profile->horaire_start ?? null,
            'horaire_end' => $profile->horaire_end ?? null,
            'moyens_paiement' => $moyensPaiement,
            'moyens_transport' => $moyensTransport,
            'jours_disponibles' => $joursDisponibles,
            'gerant_name' => $profile->gerant_name ?? null,
            'responsable_name' => $profile->responsable_name ?? null,
            'disponible' => $profile->disponible ?? true,
            'is_verified' => $user->is_verified ?? false,
            'created_at' => $user->created_at
        ];
    }

    private function formatBasicData($user)
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'type' => $user->role_name,
            'role' => $user->role_name,
            'role_name' => $user->role_name,
            'email' => $user->email,
            'phone' => $user->phone,
            'moyens_paiement' => [],
            'moyens_transport' => [],
            'jours_disponibles' => [],
            'is_verified' => $user->is_verified ?? false,
            'created_at' => $user->created_at
        ];
    }

    private function parseJsonField($field, $fieldName)
    {
        if (empty($field)) {
            Log::info("Field $fieldName is empty or null");
            return [];
        }

        Log::info("Parsing $fieldName: " . $field);
        
        // If it's already an array, return it
        if (is_array($field)) {
            return $field;
        }

        // Try to decode JSON first
        $decoded = json_decode($field, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            Log::info("Successfully parsed $fieldName as JSON array");
            // Clean up any escaped quotes or extra formatting
            return array_map(function($item) {
                if (is_string($item)) {
                    // Remove escaped quotes and clean up formatting
                    $cleaned = str_replace(['\"', '\\"'], '', $item);
                    $cleaned = trim($cleaned, '"\'');
                    // Handle unicode escape sequences
                    $cleaned = json_decode('"' . $cleaned . '"');
                    return $cleaned ?: $item;
                }
                return $item;
            }, $decoded);
        }

        // If JSON decode failed, try to handle as malformed JSON string
        if (is_string($field)) {
            // Handle malformed JSON like "\"Item1\"\"Item2\"\"Item3\""
            $field = str_replace(['\"', '\\"'], '"', $field);
            
            // Try to fix common JSON formatting issues
            if (strpos($field, '""') !== false) {
                // Handle cases like "Item1""Item2""Item3"
                $field = str_replace('""', '","', $field);
                $field = '["' . trim($field, '"') . '"]';
            }
            
            // Try JSON decode again
            $decoded = json_decode($field, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                Log::info("Successfully parsed $fieldName after cleanup");
                return array_map(function($item) {
                    if (is_string($item)) {
                        $cleaned = trim($item, '"\'');
                        // Handle unicode escape sequences
                        $cleaned = json_decode('"' . $cleaned . '"');
                        return $cleaned ?: $item;
                    }
                    return $item;
                }, $decoded);
            }
            
            // Last resort: split by common delimiters
            $cleaned = trim($field, '[]"\'');
            if (!empty($cleaned)) {
                // Try different splitting patterns
                $patterns = ['","', '""', '\\"', '","'];
                foreach ($patterns as $pattern) {
                    if (strpos($cleaned, $pattern) !== false) {
                        $items = explode($pattern, $cleaned);
                        $items = array_map(function($item) {
                            $cleaned = trim($item, '"\'\\');
                            // Handle unicode escape sequences
                            $decoded = json_decode('"' . $cleaned . '"');
                            return $decoded ?: $cleaned;
                        }, $items);
                        $items = array_filter($items, function($item) {
                            return !empty(trim($item));
                        });
                        if (!empty($items)) {
                            Log::info("Parsed $fieldName using pattern: $pattern");
                            return array_values($items);
                        }
                    }
                }
                
                // Single item case
                $singleItem = trim($cleaned, '"\'\\');
                if (!empty($singleItem)) {
                    return [$singleItem];
                }
            }
        }

        Log::warning("Could not parse $fieldName, returning empty array");
        return [];
    }
}
