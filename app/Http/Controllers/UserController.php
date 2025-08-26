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
            
            // Handle horaires as JSON format
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
}
