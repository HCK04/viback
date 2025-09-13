<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
// use Illuminate\Support\Facades\Storage; // Not required for simple '/storage' mapping
use App\Models\User;
use App\Models\Role;
use App\Models\CliniqueProfile;
use App\Models\PharmacieProfile;
use App\Models\ParapharmacieProfile;
use App\Models\LaboAnalyseProfile;
use App\Models\CentreRadiologieProfile;
use Carbon\Carbon;

class OrganizationApiController extends Controller
{
    /**
     * Register a new organization
     */
    public function register(Request $request)
    {
        try {
            DB::beginTransaction();
            
            \Log::info('Organization registration request:', $request->all());

            // Validate common fields
            $validated = $request->validate([
                'name' => 'required|string|max:100',
                'email' => 'required|string|email|max:100|unique:users',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]/',
                    'confirmed'
                ],
                'password_confirmation' => 'required|string',
                'phone' => 'required|string|max:20|unique:users',
                'role_id' => 'required|exists:roles,id',
                'nom_etablissement' => 'required|string|max:255',
                'responsable_name' => 'required|string|max:255',
                'adresse' => 'required|string|max:500',
                'ville' => 'required|string|max:100',
                'horaire_start' => 'required|string',
                'horaire_end' => 'required|string',
                'services' => 'required|string',
                'description' => 'nullable|string|max:1000',
                'org_presentation' => 'nullable|string|max:2000',
                'presentation' => 'nullable|string|max:2000',
                'services_description' => 'nullable|string|max:2000',
                'additional_info' => 'nullable|string|max:1000',
                'moyens_paiement' => 'nullable|array',
                'moyens_transport' => 'nullable|array',
                'informations_pratiques' => 'nullable|string|max:1000',
                'jours_disponibles' => 'nullable|array',
                'contact_urgence' => 'nullable|string|max:20',
                'clinic_presentation' => 'nullable|string|max:2000',
                'clinic_services_description' => 'nullable|string|max:2000',
                'guard' => 'nullable|in:0,1,true,false',
                // Gallery images
                'imgs' => 'nullable|array',
                'imgs.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120'
            ]);

            // Create user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'phone' => $validated['phone'],
                'role_id' => $validated['role_id'],
                'is_verified' => 0,
                'is_subscribed' => 0
            ]);

            $role = Role::find($validated['role_id']);
            
            // Handle file uploads
            $etablissementImagePath = null;
            if ($request->hasFile('etablissement_image')) {
                $file = $request->file('etablissement_image');
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('public/etablissements', $filename);
                $etablissementImagePath = str_replace('public/', '', $path);
            }

            $profileImagePath = null;
            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('public/profiles', $filename);
                $profileImagePath = str_replace('public/', '', $path);
            }

            // Prepare common profile data
            $commonData = [
                'user_id' => $user->id,
                'adresse' => $validated['adresse'],
                'ville' => $validated['ville'],
                'horaire_start' => $validated['horaire_start'],
                'horaire_end' => $validated['horaire_end'],
                'services' => is_string($validated['services']) ? $validated['services'] : json_encode($validated['services'], JSON_UNESCAPED_UNICODE),
                'description' => $validated['description'] ?? null,
                'org_presentation' => $validated['org_presentation'] ?? $validated['presentation'] ?? null,
                'presentation' => $validated['presentation'] ?? null,
                'services_description' => $validated['services_description'] ?? null,
                'additional_info' => $validated['additional_info'] ?? null,
                'responsable_name' => $validated['responsable_name'],
                'etablissement_image' => $etablissementImagePath,
                'profile_image' => $profileImagePath,
                'rating' => 0.0,
                'disponible' => true,
                'vacation_mode' => false,
                'vacation_auto_reactivate_date' => null,
                'gallery' => null,
                'moyens_paiement' => $validated['moyens_paiement'] ? json_encode($validated['moyens_paiement'], JSON_UNESCAPED_UNICODE) : null,
                'moyens_transport' => $validated['moyens_transport'] ? json_encode($validated['moyens_transport'], JSON_UNESCAPED_UNICODE) : null,
                'informations_pratiques' => $validated['informations_pratiques'] ?? null,
                'jours_disponibles' => $validated['jours_disponibles'] ? json_encode($validated['jours_disponibles'], JSON_UNESCAPED_UNICODE) : null,
                'contact_urgence' => $validated['contact_urgence'] ?? null,
                'guard' => isset($validated['guard']) ? (bool)$validated['guard'] : false
            ];

            // Create organization profile based on type
            switch ($role->name) {
                case 'clinique':
                    // Log incoming data for debugging
                    \Log::info('Clinic Registration Data:', [
                        'validated' => $validated,
                        'responsable_name' => $validated['responsable_name'] ?? 'NOT PROVIDED',
                        'clinic_presentation' => $validated['clinic_presentation'] ?? 'NOT PROVIDED',
                        'clinic_services_description' => $validated['clinic_services_description'] ?? 'NOT PROVIDED'
                    ]);
                    
                    $profileData = array_merge($commonData, [
                        'nom_clinique' => $validated['nom_etablissement'],
                        'responsable_name' => $validated['responsable_name'] ?? null,
                        'clinic_presentation' => $validated['clinic_presentation'] ?? null,
                        'clinic_services_description' => $validated['clinic_services_description'] ?? null
                    ]);
                    
                    \Log::info('Clinic Profile Data to Create:', $profileData);
                    
                    $profile = CliniqueProfile::create($profileData);
                    break;

                case 'pharmacie':
                    $profileData = array_merge($commonData, [
                        'nom_pharmacie' => $validated['nom_etablissement'],
                        'gerant_name' => $validated['responsable_name'],
                        'horaires' => json_encode([
                            'start' => $validated['horaire_start'],
                            'end' => $validated['horaire_end']
                        ])
                    ]);
                    $profile = PharmacieProfile::create($profileData);
                    break;

                case 'parapharmacie':
                    $profileData = array_merge($commonData, [
                        'nom_parapharmacie' => $validated['nom_etablissement'],
                        'gerant_name' => $validated['responsable_name'],
                        'horaires' => json_encode([
                            'start' => $validated['horaire_start'],
                            'end' => $validated['horaire_end']
                        ])
                    ]);
                    $profile = ParapharmacieProfile::create($profileData);
                    break;

                case 'labo_analyse':
                    $profileData = array_merge($commonData, [
                        'nom_labo' => $validated['nom_etablissement'],
                        'gerant_name' => $validated['responsable_name'],
                        'horaires' => json_encode([
                            'start' => $validated['horaire_start'],
                            'end' => $validated['horaire_end']
                        ])
                    ]);
                    $profile = LaboAnalyseProfile::create($profileData);
                    break;

                case 'centre_radiologie':
                    $profileData = array_merge($commonData, [
                        'nom_centre' => $validated['nom_etablissement'],
                        'gerant_name' => $validated['responsable_name'],
                        'horaires' => json_encode([
                            'start' => $validated['horaire_start'],
                            'end' => $validated['horaire_end']
                        ])
                    ]);
                    $profile = CentreRadiologieProfile::create($profileData);
                    break;

                default:
                    throw new \Exception('Invalid organization type');
            }

            // Handle gallery images (imgs[])
            if ($request->hasFile('imgs')) {
                try {
                    $imgsPaths = [];
                    $files = $request->file('imgs');
                    foreach ($files as $i => $file) {
                        if ($i >= 6) break; // limit to 6
                        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $path = $file->storeAs('public/imgs', $filename);
                        $imgsPaths[] = str_replace('public/', '', $path);
                    }
                    if (!empty($imgsPaths)) {
                        $profile->imgs = json_encode($imgsPaths, JSON_UNESCAPED_UNICODE);
                        $profile->save();
                    }
                } catch (\Exception $e) {
                    \Log::error('Failed to store organization imgs:', ['error' => $e->getMessage()]);
                }
            }

            DB::commit();

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            \Log::info('Organization registered successfully:', [
                'user_id' => $user->id,
                'profile_id' => $profile->id,
                'type' => $role->name
            ]);

            return response()->json([
                'message' => 'Organization registered successfully',
                'token' => $token,
                'user' => $user,
                'profile' => $profile
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Organization registration error:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'request' => $request->all()
            ]);

            return response()->json([
                'message' => 'Organization registration failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all organizations
     */
    public function index(Request $request)
    {
        try {
            $organizations = [];
            $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
            $includeUnverified = filter_var($request->query('include_unverified'), FILTER_VALIDATE_BOOLEAN);

            foreach ($orgTypes as $type) {
                $modelClass = $this->getModelClass($type);
                $nameField = $this->getNameField($type);

                $query = $modelClass::with('user.role')
                    ->whereHas('user', function($query) use ($includeUnverified) {
                        if (!$includeUnverified) {
                            $query->where('is_verified', true);
                        }
                    });

                $profiles = $query->get();

                foreach ($profiles as $profile) {
                    try {
                        // Normalize gallery/images
                        $galleryArr = [];
                        if (!empty($profile->gallery)) {
                            $galleryArr = is_string($profile->gallery) ? json_decode($profile->gallery, true) : (is_array($profile->gallery) ? $profile->gallery : []);
                            $galleryArr = is_array($galleryArr) ? array_map([$this, 'normalizeMediaPath'], $galleryArr) : [];
                        }
                        $imgsArr = [];
                        if (!empty($profile->imgs)) {
                            $imgsArr = is_string($profile->imgs) ? json_decode($profile->imgs, true) : (is_array($profile->imgs) ? $profile->imgs : []);
                            $imgsArr = is_array($imgsArr) ? array_map([$this, 'normalizeMediaPath'], $imgsArr) : [];
                        }
                        $organizations[] = [
                            'id' => $profile->user->id,
                            'name' => $profile->$nameField ?? $profile->user->name,
                            'type' => $type,
                            'email' => $profile->user->email ?? '',
                            'phone' => $profile->user->phone ?? '',
                            'adresse' => $profile->adresse ?? '',
                            'ville' => $profile->ville ?? '',
                            'location' => $profile->adresse ?? '',
                            'rating' => $profile->rating ?? 0,
                            'description' => $profile->description ?? '',
                            'org_presentation' => $profile->org_presentation ?? '',
                            'services_description' => $profile->services_description ?? '',
                            'additional_info' => $profile->additional_info ?? '',
                            'presentation' => $profile->presentation ?? '',
                            'informations_pratiques' => $profile->informations_pratiques ?? '',
                            'contact_urgence' => $profile->contact_urgence ?? '',
                            'etablissement_image' => $this->normalizeMediaPath($profile->etablissement_image ?? ''),
                            'profile_image' => $this->normalizeMediaPath($profile->profile_image ?? ''),
                            'gallery' => $galleryArr,
                            'imgs' => $imgsArr,
                            'horaires' => [
                                'start' => $profile->horaire_start ?? '',
                                'end' => $profile->horaire_end ?? ''
                            ],
                            'services' => $profile->services ? (is_string($profile->services) ? json_decode($profile->services, true) : $profile->services) : [],
                            'moyens_paiement' => $profile->moyens_paiement ? (is_string($profile->moyens_paiement) ? json_decode($profile->moyens_paiement, true) : $profile->moyens_paiement) : [],
                            'moyens_transport' => $profile->moyens_transport ? (is_string($profile->moyens_transport) ? json_decode($profile->moyens_transport, true) : $profile->moyens_transport) : [],
                            'jours_disponibles' => $profile->jours_disponibles ? (is_string($profile->jours_disponibles) ? json_decode($profile->jours_disponibles, true) : $profile->jours_disponibles) : [],
                            'responsable_name' => $profile->responsable_name ?? '',
                            'gerant_name' => $profile->gerant_name ?? '',
                            'disponible' => $profile->disponible ?? true,
                            'vacation_mode' => $profile->vacation_mode ?? false,
                            'guard' => (bool)($profile->guard ?? false),
                            'is_verified' => $profile->user->is_verified ?? false,
                            'created_at' => $profile->created_at
                        ];
                    } catch (\Exception $e) {
                        \Log::error('Error processing organization profile:', [
                            'profile_id' => $profile->id ?? 'unknown',
                            'type' => $type,
                            'error' => $e->getMessage()
                        ]);
                        continue;
                    }
                }
            }

            return response()->json($organizations);
        } catch (\Exception $e) {
            \Log::error('Error fetching organizations:', [
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);
            return response()->json(['error' => 'Error fetching organizations'], 500);
        }
    }

    /**
     * Get single organization by ID
     */
    public function show($id)
    {
        try {
            $user = User::with('role')->find($id);
            
            if (!$user) {
                return response()->json(['message' => 'Organization not found'], 404);
            }

            $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
            $roleName = $user->role->name ?? null;
            if (!$roleName || !in_array($roleName, $orgTypes)) {
                // Try to infer org type from existing profile rows
                $roleName = null;
                if (CliniqueProfile::where('user_id', $id)->exists()) {
                    $roleName = 'clinique';
                } elseif (PharmacieProfile::where('user_id', $id)->exists()) {
                    $roleName = 'pharmacie';
                } elseif (ParapharmacieProfile::where('user_id', $id)->exists()) {
                    $roleName = 'parapharmacie';
                } elseif (LaboAnalyseProfile::where('user_id', $id)->exists()) {
                    $roleName = 'labo_analyse';
                } elseif (CentreRadiologieProfile::where('user_id', $id)->exists()) {
                    $roleName = 'centre_radiologie';
                }
                if (!$roleName) {
                    return response()->json(['message' => 'Not an organization'], 404);
                }
            }

            $modelClass = $this->getModelClass($roleName);
            $nameField = $this->getNameField($roleName);
            
            $profile = $modelClass::where('user_id', $id)->first();
            
            if (!$profile) {
                return response()->json(['message' => 'Organization profile not found'], 404);
            }

            // Robust decoding helpers
            $toArray = function ($value) {
                if (empty($value) && $value !== '0') return [];
                if (is_array($value)) return $value;
                if (is_string($value)) {
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) return $decoded;
                    // Not JSON array: treat as single path/value
                    $trimmed = trim($value);
                    return $trimmed !== '' ? [$trimmed] : [];
                }
                return [];
            };

            $gallery = array_map([$this, 'normalizeMediaPath'], $toArray($profile->gallery));
            $imgs = array_map([$this, 'normalizeMediaPath'], $toArray($profile->imgs));

            $organization = [
                'id' => $user->id,
                'name' => $profile->$nameField ?? $user->name,
                'type' => $roleName,
                'email' => $user->email,
                'phone' => $user->phone,
                'adresse' => $profile->adresse,
                'ville' => $profile->ville,
                'location' => $profile->adresse,
                'rating' => $profile->rating ?? 0,
                'description' => $profile->description,
                'org_presentation' => $profile->org_presentation,
                'services_description' => $profile->services_description,
                'additional_info' => $profile->additional_info,
                'presentation' => $profile->presentation,
                'clinic_presentation' => $profile->clinic_presentation,
                'clinic_services_description' => $profile->clinic_services_description,
                'informations_pratiques' => $profile->informations_pratiques,
                'contact_urgence' => $profile->contact_urgence,
                'etablissement_image' => $this->normalizeMediaPath($profile->etablissement_image),
                'profile_image' => $this->normalizeMediaPath($profile->profile_image),
                'gallery' => $gallery,
                'imgs' => $imgs,
                'horaires' => [
                    'start' => $profile->horaire_start,
                    'end' => $profile->horaire_end
                ],
                'horaire_start' => $profile->horaire_start,
                'horaire_end' => $profile->horaire_end,
                'services' => $toArray($profile->services),
                'moyens_paiement' => $toArray($profile->moyens_paiement),
                'moyens_transport' => $toArray($profile->moyens_transport),
                'jours_disponibles' => $toArray($profile->jours_disponibles),
                'responsable_name' => $profile->responsable_name,
                'gerant_name' => $profile->gerant_name ?? null,
                'disponible' => $profile->disponible,
                'vacation_mode' => $profile->vacation_mode ?? false,
                'guard' => (bool)($profile->guard ?? false),
                'absence_start_date' => $profile->absence_start_date,
                'absence_end_date' => $profile->absence_end_date,
                'is_verified' => $user->is_verified,
                'created_at' => $profile->created_at,
                'updated_at' => $profile->updated_at,
                // Add organization-specific name fields
                'nom_clinique' => $profile->nom_clinique ?? null,
                'nom_pharmacie' => $profile->nom_pharmacie ?? null,
                'nom_parapharmacie' => $profile->nom_parapharmacie ?? null,
                'nom_labo' => $profile->nom_labo ?? null,
                'nom_centre' => $profile->nom_centre ?? null
            ];

            return response()->json($organization);

        } catch (\Exception $e) {
            \Log::error('Error fetching organization:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'Error fetching organization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update organization profile
     */
    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $user = User::with('role')->find($id);
            
            if (!$user) {
                return response()->json(['message' => 'Organization not found'], 404);
            }

            $orgTypes = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];
            if (!in_array($user->role->name, $orgTypes)) {
                return response()->json(['message' => 'Not an organization'], 404);
            }

            // Validate update fields
            $validated = $request->validate([
                'nom_etablissement' => 'nullable|string|max:255',
                'responsable_name' => 'nullable|string|max:255',
                'adresse' => 'nullable|string|max:500',
                'ville' => 'nullable|string|max:100',
                'horaire_start' => 'nullable|string',
                'horaire_end' => 'nullable|string',
                // Accept JSON string or array for the following fields
                'services' => 'nullable',
                'description' => 'nullable|string|max:1000',
                'org_presentation' => 'nullable|string|max:2000',
                // Clinic-specific presentation fields (for clinics)
                'clinic_presentation' => 'nullable|string|max:2000',
                'clinic_services_description' => 'nullable|string|max:2000',
                'services_description' => 'nullable|string|max:2000',
                'additional_info' => 'nullable|string|max:1000',
                'presentation' => 'nullable|string|max:2000',
                'moyens_paiement' => 'nullable',
                'moyens_transport' => 'nullable',
                'informations_pratiques' => 'nullable|string|max:1000',
                'jours_disponibles' => 'nullable',
                'contact_urgence' => 'nullable|string|max:20',
                // Availability / vacation fields
                'disponible' => 'nullable|in:0,1,true,false',
                'absence_start_date' => 'nullable|date',
                'absence_end_date' => 'nullable|date',
                'vacation_mode' => 'nullable|in:0,1,true,false',
                // Pharmacy guard fields
                'guard' => 'nullable|in:0,1,true,false',
                'guard_start_date' => 'nullable|date',
                'guard_end_date' => 'nullable|date',
                // Optional user fields
                'name' => 'nullable|string|max:100',
                'email' => 'nullable|email|unique:users,email,' . $id,
                'phone' => 'nullable|string|max:20|unique:users,phone,' . $id,
                // Gallery images
                'imgs' => 'nullable|array',
                'imgs.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
                'imgs_keep' => 'nullable|array',
                'imgs_keep.*' => 'string|max:255',
            ]);

            \Log::info('Organization update input snapshot', [
                'user_id' => $id,
                'keys' => array_keys($validated),
                'jours_disponibles_raw' => $request->input('jours_disponibles'),
                'moyens_transport_raw' => $request->input('moyens_transport'),
                'moyens_paiement_raw' => $request->input('moyens_paiement'),
                'content_type' => $request->header('Content-Type')
            ]);

            $modelClass = $this->getModelClass($user->role->name);
            $nameField = $this->getNameField($user->role->name);
            
            $profile = $modelClass::where('user_id', $id)->first();
            
            if (!$profile) {
                return response()->json(['message' => 'Organization profile not found'], 404);
            }

            // Handle file uploads
            if ($request->hasFile('etablissement_image')) {
                $file = $request->file('etablissement_image');
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('public/etablissements', $filename);
                $validated['etablissement_image'] = str_replace('public/', '', $path);
            }

            if ($request->hasFile('profile_image')) {
                $file = $request->file('profile_image');
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('public/profiles', $filename);
                $validated['profile_image'] = str_replace('public/', '', $path);
            }

            // Prepare update data
            $updateData = [];
            $userUpdate = [];
            foreach ($validated as $key => $value) {
                if ($key === 'nom_etablissement') {
                    $updateData[$nameField] = $value;
                    continue;
                }
                // Route user fields to User model
                if (in_array($key, ['name', 'email', 'phone'])) {
                    $userUpdate[$key] = $value;
                    continue;
                }
                // Normalize JSON array fields
                if (in_array($key, ['services', 'moyens_paiement', 'moyens_transport', 'jours_disponibles'])) {
                    if (is_array($value)) {
                        $updateData[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } elseif (is_string($value)) {
                        $decoded = json_decode($value, true);
                        if (json_last_error() === JSON_ERROR_NONE) {
                            $updateData[$key] = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                        } else {
                            // Fallback: treat as comma-separated list
                            $updateData[$key] = json_encode(array_values(array_filter(array_map('trim', explode(',', $value)))), JSON_UNESCAPED_UNICODE);
                        }
                    } else {
                        $updateData[$key] = null;
                    }
                    continue;
                }
                // Booleans
                if (in_array($key, ['disponible', 'vacation_mode', 'guard'])) {
                    $updateData[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                    continue;
                }
                // Default assignment
                $updateData[$key] = $value;
            }

            // Clinic-specific field syncing to be robust with various frontends
            try {
                $roleName = $user->role->name ?? null;
                if ($roleName === 'clinique') {
                    // If only services_description provided, mirror to clinic_services_description
                    if (!array_key_exists('clinic_services_description', $updateData) && array_key_exists('services_description', $updateData)) {
                        $updateData['clinic_services_description'] = $updateData['services_description'];
                    }
                    // If only clinic_services_description provided, mirror back to services_description too
                    if (!array_key_exists('services_description', $updateData) && array_key_exists('clinic_services_description', $updateData)) {
                        $updateData['services_description'] = $updateData['clinic_services_description'];
                    }
                    // Presentation: if only generic provided, mirror to clinic_presentation
                    if (!array_key_exists('clinic_presentation', $updateData) && array_key_exists('presentation', $updateData)) {
                        $updateData['clinic_presentation'] = $updateData['presentation'];
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Clinic field sync skipped', ['error' => $e->getMessage()]);
            }

            // Add horaires field for organizations that have it
            if (in_array($user->role->name, ['pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'])) {
                if (isset($validated['horaire_start']) && isset($validated['horaire_end'])) {
                    $updateData['horaires'] = json_encode([
                        'start' => $validated['horaire_start'],
                        'end' => $validated['horaire_end']
                    ]);
                }
            }

            // Update user basic fields if provided
            if (!empty($userUpdate)) {
                foreach ($userUpdate as $uKey => $uVal) {
                    if ($uVal !== null) {
                        $user->{$uKey} = $uVal;
                    }
                }
                $user->save();
            }

            // If pharmacy, apply guard defaults and constraints
            if ($user->role->name === 'pharmacie') {
                if (array_key_exists('guard', $updateData)) {
                    $guardOn = (bool)$updateData['guard'];
                    if ($guardOn) {
                        // Ensure start/end dates exist; default to [today, today+7d]
                        if (empty($validated['guard_start_date'])) {
                            $updateData['guard_start_date'] = Carbon::now()->toDateString();
                        }
                        if (empty($validated['guard_end_date'])) {
                            $updateData['guard_end_date'] = Carbon::now()->addWeek()->toDateString();
                        }
                    } else {
                        // Clearing guard turns dates off
                        $updateData['guard_start_date'] = null;
                        $updateData['guard_end_date'] = null;
                    }
                }
            } else {
                // Non-pharmacy: never allow guard
                unset($updateData['guard'], $updateData['guard_start_date'], $updateData['guard_end_date']);
            }

            \Log::info('Organization update prepared data', [
                'user_id' => $id,
                'update_keys' => array_keys($updateData),
                'user_update_keys' => array_keys($userUpdate),
                'update_preview' => array_intersect_key($updateData, array_flip(['services','moyens_transport','moyens_paiement','jours_disponibles','informations_pratiques','contact_urgence']))
            ]);

            $profile->update($updateData);

            \Log::info('Organization profile updated persisted', [
                'user_id' => $id,
                'profile_id' => $profile->id,
                'updated_at' => $profile->fresh()->updated_at,
            ]);

            // Handle gallery updates per slot: combine kept paths and new files according to their indices (0..5)
            try {
                $hasKeep = $request->has('imgs_keep');
                $hasNew = $request->hasFile('imgs');
                $clearAll = filter_var($request->input('clear_imgs'), FILTER_VALIDATE_BOOLEAN) || $request->input('clear_imgs') === '1';

                if ($clearAll && !$hasKeep && !$hasNew) {
                    $profile->imgs = json_encode([], JSON_UNESCAPED_UNICODE);
                    $profile->save();
                } elseif ($hasKeep || $hasNew) {
                    $newImgs = [];
                    for ($idx = 0; $idx < 6; $idx++) {
                        // Prefer kept path if provided at this index
                        $keepPath = $request->input("imgs_keep.$idx");
                        if (!is_null($keepPath)) {
                            $p = (string)$keepPath;
                            // Normalize incoming path such as '/storage/imgs/xxx.jpg' or 'public/imgs/xxx.jpg' to 'imgs/xxx.jpg'
                            $p = ltrim($p, '/');
                            if (stripos($p, 'storage/') === 0) {
                                $p = substr($p, 8); // remove 'storage/'
                            }
                            if (stripos($p, 'public/') === 0) {
                                $p = substr($p, 7); // remove 'public/'
                            }
                            // Only allow gallery under imgs/
                            if (stripos($p, 'imgs/') !== 0) {
                                // If it's another folder, attempt to place under imgs/ keeping filename
                                $filename = basename($p);
                                $p = 'imgs/' . $filename;
                            }
                            $newImgs[] = $p;
                            continue;
                        }

                        // Otherwise, check for new file at this index
                        if ($request->hasFile("imgs.$idx")) {
                            $file = $request->file("imgs.$idx");
                            if ($file && $file->isValid()) {
                                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                                $path = $file->storeAs('public/imgs', $filename);
                                $newImgs[] = str_replace('public/', '', $path);
                            }
                        }
                    }

                    // Trim to max 6 and save if we have any entries (including empty to clear all)
                    $newImgs = array_slice($newImgs, 0, 6);
                    $profile->imgs = !empty($newImgs) ? json_encode($newImgs, JSON_UNESCAPED_UNICODE) : json_encode([], JSON_UNESCAPED_UNICODE);
                    $profile->save();
                }
            } catch (\Exception $e) {
                \Log::error('Failed to update organization imgs per-slot:', ['id' => $id, 'error' => $e->getMessage()]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Organization updated successfully',
                'profile' => $profile->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error updating organization:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'message' => 'Error updating organization',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Normalize a stored media path to a web-accessible '/storage/...' URL path.
     */
    private function normalizeMediaPath($path)
    {
        if ($path === null || $path === '') {
            return $path;
        }
        $p = str_replace('\\', '/', (string) $path);
        $p = ltrim($p);
        // Absolute URL stays as-is
        if (preg_match('#^https?://#i', $p)) {
            return $p;
        }
        // Strip known prefixes
        $p = preg_replace('#^/storage/public/#i', '', $p);
        $p = preg_replace('#^storage/public/#i', '', $p);
        $p = preg_replace('#^/public/#i', '', $p);
        $p = preg_replace('#^public/#i', '', $p);
        $p = preg_replace('#^/storage/#i', '', $p);
        $p = preg_replace('#^storage/#i', '', $p);
        $p2 = ltrim($p, '/');
        // Known public disk directories -> serve under /storage
        if (preg_match('#^(imgs|images|uploads|upload|profiles|etablissements|clinic|clinique|clinics|parapharmacie|parapharmacies|parapharmacie_profiles|pharmacie|pharmacies|pharmacy|pharmacie_profiles|labo|labo_analyse|laboratoire|radiologie|centre_radiologie|etablissement_images|gallery)/#i', $p2)) {
            return '/storage/' . $p2;
        }
        // Heuristic: image files default to /storage
        if (preg_match('#\.(png|jpe?g|webp|gif|bmp|svg)$#i', $p2)) {
            return '/storage/' . $p2;
        }
        // Fallback: ensure leading slash
        return '/' . $p2;
    }

    /**
     * Get model class for organization type
     */
    private function getModelClass($type)
    {
        switch ($type) {
            case 'clinique':
                return CliniqueProfile::class;
            case 'pharmacie':
                return PharmacieProfile::class;
            case 'parapharmacie':
                return ParapharmacieProfile::class;
            case 'labo_analyse':
                return LaboAnalyseProfile::class;
            case 'centre_radiologie':
                return CentreRadiologieProfile::class;
            default:
                throw new \Exception('Invalid organization type');
        }
    }

    /**
     * Get name field for organization type
     */
    private function getNameField($type)
    {
        switch ($type) {
            case 'clinique':
                return 'nom_clinique';
            case 'pharmacie':
                return 'nom_pharmacie';
            case 'parapharmacie':
                return 'nom_parapharmacie';
            case 'labo_analyse':
                return 'nom_labo';
            case 'centre_radiologie':
                return 'nom_centre';
            default:
                throw new \Exception('Invalid organization type');
        }
    }

    /**
     * Search organizations by city and type
     */
    public function search(Request $request)
    {
        try {
            $ville = $request->get('ville');
            $type = $request->get('type');
            $includeUnverified = filter_var($request->get('include_unverified'), FILTER_VALIDATE_BOOLEAN);
            
            $organizations = [];
            $orgTypes = $type ? [$type] : ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie'];

            foreach ($orgTypes as $orgType) {
                try {
                    $modelClass = $this->getModelClass($orgType);
                    $nameField = $this->getNameField($orgType);
                    
                    $query = $modelClass::with('user.role')
                        ->whereHas('user', function($q) use ($includeUnverified) {
                            if (!$includeUnverified) {
                                $q->where('is_verified', true);
                            }
                        });

                    if ($ville && $ville !== 'Toutes les villes') {
                        $query->where('ville', 'LIKE', "%{$ville}%");
                    }

                    $profiles = $query->get();

                    foreach ($profiles as $profile) {
                        try {
                            if (!$profile->user) {
                                continue;
                            }

                            $organizations[] = [
                                'id' => $profile->user->id,
                                'name' => $profile->$nameField ?? $profile->user->name,
                                'type' => $orgType,
                                'ville' => $profile->ville ?? '',
                                'adresse' => $profile->adresse ?? '',
                                'rating' => $profile->rating ?? 0,
                                'etablissement_image' => $this->normalizeMediaPath($profile->etablissement_image ?? ''),
                                'services' => $profile->services ? (is_string($profile->services) ? json_decode($profile->services, true) : $profile->services) : [],
                                'horaires' => [
                                    'start' => $profile->horaire_start ?? '',
                                    'end' => $profile->horaire_end ?? ''
                                ],
                                // Descriptive fields included for frontend search display (labs, radiology, etc.)
                                'description' => $profile->description ?? '',
                                'org_presentation' => $profile->org_presentation ?? '',
                                'services_description' => $profile->services_description ?? '',
                                'presentation' => $profile->presentation ?? '',
                                'vacation_mode' => $profile->vacation_mode ?? false,
                                'guard' => (bool)($profile->guard ?? false)
                            ];
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            return response()->json($organizations);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error searching organizations'], 500);
        }
    }
}
