<?php


namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\DB;

class OrganisationController extends Controller
{
    /**
     * Get all organizations
     */
    public function index(Request $request)
    {
        $organizations = [];

        // Get all organization types
        $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];

        foreach ($orgTypes as $type) {
            $tableName = $type . '_profiles';
            
            // Get organizations of this type - REMOVE is_verified filter temporarily for testing
            $orgs = DB::table('users')
                ->join('roles', 'users.role_id', '=', 'roles.id')
                ->join($tableName, 'users.id', '=', $tableName . '.user_id')
                ->where('roles.name', $type)
                // ->where('users.is_verified', true)  // Comment out for testing
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.phone',
                    'users.is_verified',
                    'roles.name as type',
                    $tableName . '.etablissement_image',
                    $tableName . '.rating',
                    $tableName . '.description',
                    $tableName . '.adresse as location',
                    $tableName . '.horaire_start',
                    $tableName . '.horaire_end'
                )
                ->get();

            foreach ($orgs as $org) {
                // Get organization-specific name field
                $orgName = $org->name;
                switch ($type) {
                    case 'clinique':
                        $specific = DB::table('clinique_profiles')->where('user_id', $org->id)->first();
                        if ($specific && $specific->nom_clinique) {
                            $orgName = $specific->nom_clinique;
                        }
                        break;
                    case 'pharmacie':
                        $specific = DB::table('pharmacie_profiles')->where('user_id', $org->id)->first();
                        if ($specific && $specific->nom_pharmacie) {
                            $orgName = $specific->nom_pharmacie;
                        }
                        break;
                    case 'parapharmacie':
                        $specific = DB::table('parapharmacie_profiles')->where('user_id', $org->id)->first();
                        if ($specific && $specific->nom_parapharmacie) {
                            $orgName = $specific->nom_parapharmacie;
                        }
                        break;
                    case 'labo_analyse':
                        $specific = DB::table('labo_analyse_profiles')->where('user_id', $org->id)->first();
                        if ($specific && $specific->nom_labo) {
                            $orgName = $specific->nom_labo;
                        }
                        break;
                    case 'centre_radiologie':
                        $specific = DB::table('centre_radiologie_profiles')->where('user_id', $org->id)->first();
                        if ($specific && $specific->nom_centre) {
                            $orgName = $specific->nom_centre;
                        }
                        break;
                }

                // Extract first image from etablissement_image
                $galleryImages = null;
                if ($org->etablissement_image) {
                    $galleryImages = $org->etablissement_image;
                }

                $organizations[] = [
                    'id' => $org->id,
                    'name' => $orgName,
                    'type' => $org->type,
                    'location' => $org->location,
                    'rating' => $org->rating ?: 0, // Default to 0 if no rating
                    'etablissement_image' => $galleryImages,
                    'description' => $org->description ?: 'Description par défaut pour ' . $orgName,
                    'phone' => $org->phone,
                    'email' => $org->email,
                    'horaires' => [
                        'start' => $org->horaire_start,
                        'end' => $org->horaire_end
                    ],
                    'is_verified' => $org->is_verified, // Add for debugging
                    'available' => true
                ];
            }
        }

        return response()->json($organizations);
    }

    /**
     * Debug organizations data
     */
    public function debug(Request $request)
    {
        $debug = [];
        
        // Check role categories and roles
        $debug['role_categories'] = DB::table('role_categories')->get();
        $debug['roles'] = DB::table('roles')->get();
        
        // Check users with organization roles
        $orgRoles = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
        $debug['organization_users'] = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->whereIn('roles.name', $orgRoles)
            ->select('users.*', 'roles.name as role_name')
            ->get();

        // Check each organization type separately
        $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
        
        foreach ($orgTypes as $type) {
            $tableName = $type . '_profiles';
            
            // Check if table exists and get structure
            try {
                $debug['table_structures'][$tableName] = DB::select("DESCRIBE $tableName");
                
                // Get all records from this table
                $debug['table_data'][$tableName] = DB::table($tableName)->get();
                
                // Try the join query for this specific type
                $debug['join_queries'][$type] = DB::table('users')
                    ->join('roles', 'users.role_id', '=', 'roles.id')
                    ->join($tableName, 'users.id', '=', $tableName . '.user_id')
                    ->where('roles.name', $type)
                    ->select(
                        'users.id',
                        'users.name',
                        'users.email',
                        'users.phone',
                        'users.is_verified',
                        'roles.name as type',
                        $tableName . '.etablissement_image',
                        $tableName . '.rating',
                        $tableName . '.description',
                        $tableName . '.adresse as location',
                        $tableName . '.horaire_start',
                        $tableName . '.horaire_end'
                    )
                    ->get();
                    
                // Count verified vs unverified
                $debug['verification_counts'][$type] = [
                    'total' => DB::table('users')
                        ->join('roles', 'users.role_id', '=', 'roles.id')
                        ->join($tableName, 'users.id', '=', $tableName . '.user_id')
                        ->where('roles.name', $type)
                        ->count(),
                    'verified' => DB::table('users')
                        ->join('roles', 'users.role_id', '=', 'roles.id')
                        ->join($tableName, 'users.id', '=', $tableName . '.user_id')
                        ->where('roles.name', $type)
                        ->where('users.is_verified', true)
                        ->count(),
                ];
                        
            } catch (\Exception $e) {
                $debug['errors'][$tableName] = $e->getMessage();
            }
        }

        return response()->json($debug, 200, [], JSON_PRETTY_PRINT);
    }

    /**
     * Get single organization details (public, no auth required)
     */
    public function publicShow($id)
    {
        \Log::info("OrganisationController::publicShow called with ID: $id");
        
        // Get the user and their role
        $user = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $id)
            ->select('users.*', 'roles.name as role_name')
            ->first();

        if (!$user) {
            \Log::error("User not found for ID: $id");
            return response()->json(['message' => 'Organization not found'], 404);
        }

        \Log::info("User found: " . json_encode(['id' => $user->id, 'name' => $user->name, 'role' => $user->role_name]));

        $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
        if (!in_array($user->role_name, $orgTypes)) {
            \Log::error("User is not an organization. Role: " . $user->role_name);
            return response()->json(['message' => 'Not an organization', 'role' => $user->role_name], 404);
        }

        // Get organization profile data
        $tableName = $user->role_name . '_profiles';
        \Log::info("Looking for profile in table: $tableName");
        
        $profile = DB::table($tableName)->where('user_id', $id)->first();

        if (!$profile) {
            \Log::error("Profile not found in table: $tableName for user_id: $id");
            return response()->json(['message' => 'Organization profile not found', 'table' => $tableName], 404);
        }

        \Log::info("Profile found in $tableName: " . json_encode($profile));

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

        // Extract gallery images - check etablissement_image field
        $galleryImages = [];
        if (isset($profile->etablissement_image) && $profile->etablissement_image) {
            $galleryImages = [$profile->etablissement_image];
        }

        // Parse horaires - use horaire_start and horaire_end fields directly
        $horaireStart = $profile->horaire_start ?? null;
        $horaireEnd = $profile->horaire_end ?? null;
        $horaires = null;
        if ($horaireStart && $horaireEnd) {
            $horaires = [
                'start' => $horaireStart,
                'end' => $horaireEnd
            ];
        }

        // Parse services for labo and centre_radiologie
        $services = [];
        if (isset($profile->services) && $profile->services) {
            $services = json_decode($profile->services, true) ?: [];
        }

        $organization = [
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
            'rating' => $profile->rating ?: 0,
            'description' => $profile->description ?? null,
            'org_presentation' => $profile->org_presentation ?? null,
            'services_description' => $profile->services_description ?? null,
            'additional_info' => $profile->additional_info ?? null,
            'informations_pratiques' => $profile->informations_pratiques ?? null,
            'contact_urgence' => $profile->contact_urgence ?? null,
            'presentation' => $profile->presentation ?? null,
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
            'is_verified' => $user->is_verified,
            'responsable_name' => $profile->responsable_name ?? null,
            'gerant_name' => $profile->gerant_name ?? null,
            'nbr_personnel' => $profile->nbr_personnel ?? null,
            'disponible' => $profile->disponible ?? true,
            'vacation_mode' => $profile->vacation_mode ?? false,
            'absence_start_date' => $profile->absence_start_date ?? null,
            'absence_end_date' => $profile->absence_end_date ?? null,
            'created_at' => $user->created_at,
            // Add organization-specific name fields
            'nom_clinique' => $profile->nom_clinique ?? null,
            'nom_pharmacie' => $profile->nom_pharmacie ?? null,
            'nom_parapharmacie' => $profile->nom_parapharmacie ?? null,
            'nom_labo' => $profile->nom_labo ?? null,
            'nom_centre' => $profile->nom_centre ?? null
        ];

        return response()->json($organization);
    }

    /**
     * Get single organization details (requires auth)
     */
    public function show($id)
    {
        return $this->publicShow($id);
    }
}
