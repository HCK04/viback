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
                    $tableName . '.gallery',
                    $tableName . '.etablissement_image',
                    $tableName . '.rating',
                    $tableName . '.description',
                    $tableName . '.adresse as location',
                    $tableName . '.horaires'
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

                // Extract first image from gallery JSON or etablissement_image
                $galleryImages = null;
                if ($org->gallery) {
                    $gallery = json_decode($org->gallery, true);
                    if (is_array($gallery) && !empty($gallery)) {
                        $galleryImages = $gallery[0]; // Get first image
                    }
                } elseif ($org->etablissement_image) {
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
                    'horaires' => $org->horaires,
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
                        $tableName . '.gallery',
                        $tableName . '.rating',
                        $tableName . '.description',
                        $tableName . '.adresse as location',
                        $tableName . '.horaires'
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
     * Get single organization details
     */
    public function show($id)
    {
        // Get the user and their role
        $user = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('users.id', $id)
            ->select('users.*', 'roles.name as role_name')
            ->first();

        if (!$user) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
        if (!in_array($user->role_name, $orgTypes)) {
            return response()->json(['message' => 'Not an organization'], 404);
        }

        // Get organization profile data
        $tableName = $user->role_name . '_profiles';
        $profile = DB::table($tableName)->where('user_id', $id)->first();

        if (!$profile) {
            return response()->json(['message' => 'Organization profile not found'], 404);
        }

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

        // Extract gallery images - check both gallery and etablissement_image fields
        $galleryImages = [];
        if ($profile->gallery) {
            $gallery = json_decode($profile->gallery, true);
            if (is_array($gallery)) {
                $galleryImages = $gallery;
            }
        } elseif (isset($profile->etablissement_image) && $profile->etablissement_image) {
            // Fallback to etablissement_image field if gallery is empty
            $galleryImages = [$profile->etablissement_image];
        }

        // Parse horaires
        $horaires = null;
        if ($profile->horaires) {
            $horaires = json_decode($profile->horaires, true);
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
            'email' => $user->email,
            'phone' => $user->phone,
            'location' => $profile->adresse,
            'rating' => $profile->rating ?: 0,
            'description' => $profile->description ?: 'Description par défaut pour ' . $orgName,
            'gallery' => $galleryImages,
            'horaires' => $horaires,
            'services' => $services,
            'is_verified' => $user->is_verified,
            'gerant_name' => $profile->gerant_name ?? null,
            'nbr_personnel' => $profile->nbr_personnel ?? null,
            'localisation' => $profile->localisation ?? null,
            'disponible' => $profile->disponible ?? true,
            'created_at' => $user->created_at
        ];

        return response()->json($organization);
    }
}
