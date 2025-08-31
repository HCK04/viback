<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Get current authenticated user with role information
     */
    public function user()
    {
        $user = auth()->user();
        return response()->json($user->load('role'));
    }

    /**
     * Get user profile
     */
    public function profile()
    {
        $user = auth()->user();
        
        // Ensure user has a profile
        if (!$user->profile) {
            $user->profile()->create([
                'allergies' => 'Aucune',
                'chronic_diseases' => 'Aucune',
                'gender' => '',
                'blood_type' => '',
                'age' => null
            ]);
        }
        
        // Add debugging
        \Log::info('User profile data:', [
            'user_id' => $user->id,
            'role_id' => $user->role_id,
            'medecin_profile' => $user->medecinProfile ? [
                'id' => $user->medecinProfile->id,
                'specialty' => $user->medecinProfile->specialty,
                'experience_years' => $user->medecinProfile->experience_years,
                'adresse' => $user->medecinProfile->adresse,
                'horaires' => $user->medecinProfile->horaires,
            ] : null
        ]);
        
        // Load all profile types based on user role
        $profileRelations = ['profile', 'patientProfile'];
        
        // Add professional profiles based on role
        // Check multiple role IDs that could be doctors
        if (in_array($user->role_id, [2, 4])) { // Medecin role (2 from schema, 4 from your user)
            $profileRelations[] = 'medecinProfile';
        }
        
        return response()->json($user->load($profileRelations));
    }

    /**
     * Update user profile avatar
     */
    public function updateProfileAvatar(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'avatar' => 'required|image|max:3072', // 3MB max
        ]);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = time() . '_' . $file->getClientOriginalName();

            // Store in public/uploads/avatars folder
            $path = $file->storeAs('avatars', $filename, 'public');

            // Get or create profile
            $profile = $user->profile ?? $user->profile()->create();

            // Delete old image if exists
            if ($profile->profile_image && Storage::disk('public')->exists(str_replace('/storage/', '', $profile->profile_image))) {
                Storage::disk('public')->delete(str_replace('/storage/', '', $profile->profile_image));
            }

            // Update profile image path
            $profile->profile_image = '/storage/' . $path;
            $profile->save();

            return response()->json([
                'message' => 'Avatar updated successfully',
                'path' => '/storage/' . $path
            ]);
        }

        return response()->json(['message' => 'No file uploaded'], 400);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'age' => 'nullable|numeric',
            'gender' => 'nullable|string',
            'blood_type' => 'nullable|string',
            'allergies' => 'nullable',
            'chronic_diseases' => 'nullable',
            'password' => 'nullable|string|min:8|confirmed',
            // Doctor profile fields
            'specialty' => 'nullable|string',
            'other_specialty' => 'nullable|string',
            'experience_years' => 'nullable|numeric',
            'address' => 'nullable|string',
            'horaire_start' => 'nullable|string',
            'horaire_end' => 'nullable|string',
        ]);

        // Update user basic info
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;

        // Update password if provided
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Get or create profile
        $profile = $user->profile;
        if (!$profile) {
            $profile = new \App\Models\Profile();
            $profile->user_id = $user->id;
        }

        // Always save allergies/chronic_diseases as JSON array string
        $profile->age = $request->age;
        $profile->gender = $request->gender ?? '';
        $profile->blood_type = $request->blood_type ?? '';

        // Convert allergies to JSON array string if not empty
        if (is_array($request->allergies)) {
            $profile->allergies = json_encode($request->allergies);
        } elseif ($request->allergies && $request->allergies !== "Aucune") {
            // If it's a string, split and encode
            $profile->allergies = json_encode(array_map('trim', explode(',', $request->allergies)));
        } else {
            $profile->allergies = json_encode(["Aucune"]);
        }

        // Convert chronic_diseases to JSON array string if not empty
        if (is_array($request->chronic_diseases)) {
            $profile->chronic_diseases = json_encode($request->chronic_diseases);
        } elseif ($request->chronic_diseases && $request->chronic_diseases !== "Aucune") {
            $profile->chronic_diseases = json_encode(array_map('trim', explode(',', $request->chronic_diseases)));
        } else {
            $profile->chronic_diseases = json_encode(["Aucune"]);
        }

        $profile->save();

        // If this is a patient, also update patient_profile
        if ($user->role_id == 1) { // Assuming 1 is the patient role ID
            $patientProfile = $user->patientProfile;
            if ($patientProfile) {
                $patientProfile->age = $request->age;
                $patientProfile->gender = $request->gender ?? '';
                $patientProfile->blood_type = $request->blood_type ?? '';
                $patientProfile->allergies = $request->allergies ?: 'Aucune';
                $patientProfile->chronic_diseases = $request->chronic_diseases ?: 'Aucune';
                $patientProfile->save();
            }
        }

        // If this is a doctor, also update medecin_profile
        if (in_array($user->role_id, [2, 4])) { // Medecin role ID
            $medecinProfile = $user->medecinProfile;
            if (!$medecinProfile) {
                $medecinProfile = new \App\Models\MedecinProfile();
                $medecinProfile->user_id = $user->id;
            }

            // Update doctor-specific fields
            if ($request->filled('specialty')) {
                $medecinProfile->specialty = $request->specialty;
            }
            if ($request->filled('experience_years')) {
                $medecinProfile->experience_years = $request->experience_years;
            }
            if ($request->filled('address')) {
                $medecinProfile->adresse = $request->address;
            }
            if ($request->filled('ville')) {
                $medecinProfile->ville = $request->ville;
            }
            if ($request->filled('presentation')) {
                $medecinProfile->presentation = $request->presentation;
            }
            if ($request->filled('additional_info')) {
                $medecinProfile->additional_info = $request->additional_info;
            }
            
            // Handle separate time fields
            if ($request->filled('horaire_start')) {
                $medecinProfile->horaire_start = $request->horaire_start;
            }
            if ($request->filled('horaire_end')) {
                $medecinProfile->horaire_end = $request->horaire_end;
            }
            
            // Keep backward compatibility with horaires JSON
            if ($request->filled('horaire_start') && $request->filled('horaire_end')) {
                $medecinProfile->horaires = json_encode([
                    'start' => $request->horaire_start,
                    'end' => $request->horaire_end
                ]);
            }

            $medecinProfile->save();
        }

        // Log what was updated
        \Log::info('Profile updated:', [
            'user_id' => $user->id,
            'profile_data' => [
                'age' => $profile->age,
                'gender' => $profile->gender,
                'blood_type' => $profile->blood_type,
            ],
            'patient_profile' => $user->patientProfile ? [
                'age' => $user->patientProfile->age,
                'gender' => $user->patientProfile->gender,
                'blood_type' => $user->patientProfile->blood_type,
            ] : null
        ]);

        // Load appropriate profiles for response
        $profileRelations = ['profile', 'patientProfile'];
        if (in_array($user->role_id, [2, 4])) {
            $profileRelations[] = 'medecinProfile';
        }

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user->fresh()->load($profileRelations)
        ]);
    }

    /**
     * Get all users for admin panel
     */
    public function index()
    {
        try {
            $users = User::with('role')->get();
            return response()->json($users);
        } catch (\Exception $e) {
            \Log::error('Error fetching users: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch users'], 500);
        }
    }

    /**
     * Public search for healthcare professionals (restricted to your website)
     */
    public function publicSearch(Request $request)
    {
        // Check if request is from allowed origins
        $origin = $request->header('Origin') ?: $request->header('Referer');
        $allowedOrigins = [
            'http://localhost:3000',
            'http://127.0.0.1:3000',
            'https://vi-santé.com',
            'https://www.vi-santé.com',
            'https://api.vi-santé.com',
            'https://xn--vi-sant-hya.com',
            'https://www.xn--vi-sant-hya.com',
            'https://api.xn--vi-sant-hya.com'
        ];
        
        // Allow all subdomains of vi-santé.com
        $allowedPatterns = [
            '/^https?:\/\/[a-z0-9-]+\.vi-santé\.com$/',
            '/^https?:\/\/[a-z0-9-]+\.xn--vi-sant-hya\.com$/',
        ];
        
        $isAllowed = in_array($origin, $allowedOrigins) || 
                    collect($allowedPatterns)->contains(function ($pattern) use ($origin) {
                        return $origin && preg_match($pattern, $origin);
                    });
        
        if (!$isAllowed) {
            \Log::warning('Blocked request from unauthorized origin: ' . $origin);
            return response()->json(['error' => 'Unauthorized origin'], 403);
        }

        // Get proximity search parameters
        $latitude = $request->query('lat');
        $longitude = $request->query('lng');
        $radius = $request->query('radius', 5); // Default 5km radius

        try {
            \Log::info('PublicSearch called with params:', [
                'lat' => $latitude,
                'lng' => $longitude,
                'radius' => $radius,
                'origin' => $origin
            ]);

            $users = User::with([
                'role',
                'medecinProfile',
                'kineProfile',
                'orthophonisteProfile',
                'psychologueProfile',
                'cliniqueProfile',
                'pharmacieProfile',
                'parapharmacieProfile',
                'laboAnalyseProfile',
                'centreRadiologieProfile'
            ])
            ->whereHas('role', function($query) {
                // Exclude admin (id=1) and patient roles - include all healthcare professionals and organizations
                $query->whereNotIn('name', ['admin', 'patient']);
            })
            ->get();

            \Log::info('PublicSearch: Found ' . $users->count() . ' users after role filtering');

            \Log::info('Found users count:', ['count' => $users->count()]);

            $processedUsers = $users->map(function($user) use ($latitude, $longitude) {
                // Add profile data directly to user object for easier frontend access
                $profileData = null;
                $ville = null;
                
                try {
                    if ($user->medecinProfile) {
                        $profileData = $user->medecinProfile->toArray();
                        // Remove sensitive data - do NOT include carte_professionnelle
                        unset($profileData['carte_professionnelle']);
                        
                        // Decode JSON fields for frontend display
                        if (isset($profileData['diplomes']) && is_string($profileData['diplomes'])) {
                            $profileData['diplomes'] = json_decode($profileData['diplomes'], true);
                        }
                        if (isset($profileData['experiences']) && is_string($profileData['experiences'])) {
                            $profileData['experiences'] = json_decode($profileData['experiences'], true);
                        }
                        if (isset($profileData['specialty']) && is_string($profileData['specialty'])) {
                            $profileData['specialty'] = json_decode($profileData['specialty'], true);
                        }
                        
                        $ville = $user->medecinProfile->ville ?? null;
                    } elseif ($user->kineProfile) {
                        $profileData = $user->kineProfile->toArray();
                        unset($profileData['carte_professionnelle']);
                        
                        if (isset($profileData['diplomes']) && is_string($profileData['diplomes'])) {
                            $profileData['diplomes'] = json_decode($profileData['diplomes'], true);
                        }
                        if (isset($profileData['experiences']) && is_string($profileData['experiences'])) {
                            $profileData['experiences'] = json_decode($profileData['experiences'], true);
                        }
                        
                        $ville = $user->kineProfile->ville ?? null;
                    } elseif ($user->orthophonisteProfile) {
                        $profileData = $user->orthophonisteProfile->toArray();
                        unset($profileData['carte_professionnelle']);
                        
                        if (isset($profileData['diplomes']) && is_string($profileData['diplomes'])) {
                            $profileData['diplomes'] = json_decode($profileData['diplomes'], true);
                        }
                        if (isset($profileData['experiences']) && is_string($profileData['experiences'])) {
                            $profileData['experiences'] = json_decode($profileData['experiences'], true);
                        }
                        
                        $ville = $user->orthophonisteProfile->ville ?? null;
                    } elseif ($user->psychologueProfile) {
                        $profileData = $user->psychologueProfile->toArray();
                        unset($profileData['carte_professionnelle']);
                        
                        if (isset($profileData['diplomes']) && is_string($profileData['diplomes'])) {
                            $profileData['diplomes'] = json_decode($profileData['diplomes'], true);
                        }
                        if (isset($profileData['experiences']) && is_string($profileData['experiences'])) {
                            $profileData['experiences'] = json_decode($profileData['experiences'], true);
                        }
                        
                        $ville = $user->psychologueProfile->ville ?? null;
                    } elseif ($user->cliniqueProfile) {
                        $profileData = $user->cliniqueProfile->toArray();
                        $ville = $user->cliniqueProfile->ville ?? null;
                    } elseif ($user->pharmacieProfile) {
                        $profileData = $user->pharmacieProfile->toArray();
                        $ville = $user->pharmacieProfile->ville ?? null;
                    } elseif ($user->parapharmacieProfile) {
                        $profileData = $user->parapharmacieProfile->toArray();
                        $ville = $user->parapharmacieProfile->ville ?? null;
                    } elseif ($user->laboAnalyseProfile) {
                        $profileData = $user->laboAnalyseProfile->toArray();
                        $ville = $user->laboAnalyseProfile->ville ?? null;
                    } elseif ($user->centreRadiologieProfile) {
                        $profileData = $user->centreRadiologieProfile->toArray();
                        $ville = $user->centreRadiologieProfile->ville ?? null;
                    }
                } catch (\Exception $e) {
                    \Log::error('Error processing user profile:', [
                        'user_id' => $user->id,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Add ville and profile data to main user object
                $user->ville = $ville;
                $user->profile_data = $profileData;
                
                // Add distance calculation for proximity search
                if ($latitude && $longitude && $ville) {
                    $user->distance = null; // Placeholder for distance calculation
                }
                
                return $user;
            })
            ->filter(function($user) use ($latitude, $longitude, $radius) {
                // Only return users that have a ville (city) set
                if (empty($user->ville)) {
                    return false;
                }
                
                // For proximity search, return all users for now
                if ($latitude && $longitude) {
                    return true;
                }
                
                return true;
            })
            ->values(); // Reset array keys after filtering

            return response()->json([
                'data' => $processedUsers->toArray(),
                'count' => $processedUsers->count()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in public search: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return response()->json(['error' => 'Search failed: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all roles for admin panel
     */
    public function roles()
    {
        try {
            $roles = Role::all();
            return response()->json($roles);
        } catch (\Exception $e) {
            \Log::error('Error fetching roles: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch roles'], 500);
        }
    }

    /**
     * Create a new user (admin)
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'role_id' => 'required|exists:roles,id',
                'phone' => 'nullable|string|max:20',
            ]);

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role_id' => $request->role_id,
                'phone' => $request->phone,
            ]);

            return response()->json($user->load('role'), 201);
        } catch (\Exception $e) {
            \Log::error('Error creating user: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to create user'], 500);
        }
    }

    /**
     * Update a user (admin)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);

            $rules = [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $id,
                'role_id' => 'required|exists:roles,id',
                'phone' => 'nullable|string|max:20',
            ];

            if ($request->filled('password')) {
                $rules['password'] = 'string|min:8';
            }

            $request->validate($rules);

            $user->name = $request->name;
            $user->email = $request->email;
            $user->role_id = $request->role_id;
            $user->phone = $request->phone;

            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            $user->save();

            return response()->json($user->load('role'));
        } catch (\Exception $e) {
            \Log::error('Error updating user: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to update user'], 500);
        }
    }

    /**
     * Delete a user (admin)
     */
    public function destroy($id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Check for relationships if methods exist
            $hasRelationships = false;
            if (method_exists($user, 'rdvs') && $user->rdvs()->count() > 0) {
                $hasRelationships = true;
            }
            if (method_exists($user, 'annonces') && $user->annonces()->count() > 0) {
                $hasRelationships = true;
            }
            
            if ($hasRelationships) {
                return response()->json([
                    'message' => 'Cannot delete user with associated appointments or announcements'
                ], 400);
            }

            $user->delete();

            return response()->json(['message' => 'User deleted successfully']);
        } catch (\Exception $e) {
            \Log::error('Error deleting user: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to delete user'], 500);
        }
    }

    /**
     * Get public user profile by ID
     */
    public function publicShow($id)
    {
        $user = User::with('role')->find($id);
        
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }
        
        // Return basic public information
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'role' => $user->role->name ?? null,
            'is_verified' => $user->is_verified,
            'created_at' => $user->created_at
        ]);
    }
}
