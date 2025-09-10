<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\MedecinProfile;
use App\Models\KineProfile;
use App\Models\OrthophonisteProfile;
use App\Models\PsychologueProfile;
use App\Models\CliniqueProfile;
use App\Models\PharmacieProfile;
use App\Models\ParapharmacieProfile;
use App\Models\LaboAnalyseProfile;
use App\Models\CentreRadiologieProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class ProfessionalProfileController extends Controller
{
    /**
     * Get professional profile data
     */
    public function getProfile()
    {
        try {
            $user = auth()->user();
            
            \Log::info('Professional Profile API - User Data:', [
                'user_id' => $user->id,
                'user_role_id' => $user->role_id,
                'user_name' => $user->name,
                'user_email' => $user->email
            ]);
            
            // Load only relevant relations to avoid undefined relationship exceptions
            $profileRelations = ['profile'];
            $roleEntity = null;
            try { $roleEntity = $user->role; } catch (\Throwable $e) { $roleEntity = null; }
            $roleName = strtolower(($roleEntity->name ?? ($user->role_name ?? '')));
            \Log::info('Profile update: resolving professional profile', [
                'user_id' => $user->id,
                'role_name' => $roleName,
                'role_id' => $user->role_id,
            ]);
            if (in_array($roleName, ['medecin', 'doctor'])) {
                $profileRelations[] = 'medecinProfile';
            } elseif ($roleName === 'kine') {
                $profileRelations[] = 'kineProfile';
            } elseif ($roleName === 'orthophoniste') {
                $profileRelations[] = 'orthophonisteProfile';
            } elseif ($roleName === 'psychologue') {
                $profileRelations[] = 'psychologueProfile';
            } elseif ($roleName === 'clinique') {
                $profileRelations[] = 'cliniqueProfile';
            } elseif ($roleName === 'pharmacie') {
                $profileRelations[] = 'pharmacieProfile';
            } elseif ($roleName === 'parapharmacie') {
                $profileRelations[] = 'parapharmacieProfile';
            } elseif ($roleName === 'labo_analyse') {
                $profileRelations[] = 'laboAnalyseProfile';
            } elseif ($roleName === 'centre_radiologie') {
                $profileRelations[] = 'centreRadiologieProfile';
            }
            
            \Log::info('Professional Profile API - Profile Relations:', [
                'profile_relations' => $profileRelations,
                'detected_role' => $user->role_id
            ]);
            
            $userWithProfiles = $user->load($profileRelations);
            
            \Log::info('Professional Profile API - Loaded Profiles:', [
                'has_medecin_profile' => isset($userWithProfiles->medecinProfile),
                'has_kine_profile' => isset($userWithProfiles->kineProfile),
                'has_clinique_profile' => isset($userWithProfiles->cliniqueProfile),
                'has_pharmacie_profile' => isset($userWithProfiles->pharmacieProfile),
                'has_parapharmacie_profile' => isset($userWithProfiles->parapharmacieProfile),
                'has_labo_profile' => isset($userWithProfiles->laboAnalyseProfile),
                'has_centre_profile' => isset($userWithProfiles->centreRadiologieProfile)
            ]);
            
            return response()->json($userWithProfiles);
        } catch (\Exception $e) {
            \Log::error('Error getting professional profile: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to get profile: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update professional or organization profile
     */
    public function updateProfile(Request $request)
    {
        try {
            $user = auth()->user();

            // Validate basic user info
            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'password' => 'nullable|string|min:8|confirmed',
                
                // Professional profile fields
                'specialty' => 'nullable|string',
                'other_specialty' => 'nullable|string',
                'experience_years' => 'nullable|numeric',
                'address' => 'nullable|string',
                'ville' => 'nullable|string',
                'presentation' => 'nullable|string',
                'additional_info' => 'nullable|string',
                'horaire_start' => 'nullable|string',
                'horaire_end' => 'nullable|string',
                'jours_disponibles' => 'nullable|string',
                'moyens_transport' => 'nullable|string',
                'moyens_paiement' => 'nullable|string',
                'informations_pratiques' => 'nullable|string',
                'contact_urgence' => 'nullable|string',
                'carte_professionnelle' => 'nullable|string',
                'numero_carte_professionnelle' => 'nullable|string',
                'diplomes' => 'nullable|string',
                'diplomas' => 'nullable|string',
                'experiences' => 'nullable|string',
                'rating' => 'nullable|numeric',
                'disponible' => 'nullable|boolean',
                'absence_start_date' => 'nullable|date',
                'absence_end_date' => 'nullable|date',
                'rdv_patients_suivis_uniquement' => 'nullable|boolean',
                'vacation_mode' => 'nullable|boolean',
                'vacation_auto_reactivate_date' => 'nullable|date',
                
                // Organization specific fields
                'nom_clinique' => 'nullable|string',
                'nom_pharmacie' => 'nullable|string',
                'nom_parapharmacie' => 'nullable|string',
                'nom_centre' => 'nullable|string',
                'nom_labo' => 'nullable|string',
                'localisation' => 'nullable|string',
                'nbr_personnel' => 'nullable|numeric',
                'gerant_name' => 'nullable|string',
                'responsable_name' => 'nullable|string',
                'services' => 'nullable|string',
                'etablissement_image' => 'nullable|string',
                'description' => 'nullable|string',
                'org_presentation' => 'nullable|string',
                'services_description' => 'nullable|string',
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

            // Determine profile type and update accordingly
            $professionalProfile = null;
            
            // Resolve role by name instead of numeric IDs (without optional() helper)
            $roleEntity = null;
            try { $roleEntity = $user->role; } catch (\Throwable $e) { $roleEntity = null; }
            $roleName = strtolower(($roleEntity->name ?? ($user->role_name ?? '')));
            // Individual Professional Profiles
            if (in_array($roleName, ['medecin', 'doctor'])) {
                $professionalProfile = $user->medecinProfile;
                if (!$professionalProfile) { $professionalProfile = new MedecinProfile(); $professionalProfile->user_id = $user->id; }
            } elseif ($roleName === 'kine') {
                $professionalProfile = $user->kineProfile;
                if (!$professionalProfile) { $professionalProfile = new KineProfile(); $professionalProfile->user_id = $user->id; }
            } elseif ($roleName === 'orthophoniste') {
                $professionalProfile = $user->orthophonisteProfile;
                if (!$professionalProfile) { $professionalProfile = new OrthophonisteProfile(); $professionalProfile->user_id = $user->id; }
            } elseif ($roleName === 'psychologue') {
                $professionalProfile = $user->psychologueProfile;
                if (!$professionalProfile) { $professionalProfile = new PsychologueProfile(); $professionalProfile->user_id = $user->id; }
            }
            // Fallback by numeric role_id for professional roles if name did not resolve
            if (!$professionalProfile) {
                switch ((int)$user->role_id) {
                    case 2: // medecin
                    case 4: // doctor variant
                        $professionalProfile = $user->medecinProfile ?: (function() use ($user) { $p = new MedecinProfile(); $p->user_id = $user->id; return $p; })();
                        break;
                    case 3: // kine
                        $professionalProfile = $user->kineProfile ?: (function() use ($user) { $p = new KineProfile(); $p->user_id = $user->id; return $p; })();
                        break;
                    case 5: // orthophoniste
                        $professionalProfile = $user->orthophonisteProfile ?: (function() use ($user) { $p = new OrthophonisteProfile(); $p->user_id = $user->id; return $p; })();
                        break;
                    case 6: // psychologue
                        $professionalProfile = $user->psychologueProfile ?: (function() use ($user) { $p = new PsychologueProfile(); $p->user_id = $user->id; return $p; })();
                        break;
                }
            }
            // Last-resort fallback: if still null, pick any existing individual profile
            if (!$professionalProfile) {
                $professionalProfile = ($user->medecinProfile
                    ?: ($user->kineProfile
                    ?: ($user->orthophonisteProfile
                    ?: ($user->psychologueProfile ?: null))));
            }
            // Organization Profiles fallback by numeric role_id
            if (!$professionalProfile) {
                if (in_array($user->role_id, [7])) { // Clinique role
                    $professionalProfile = $user->cliniqueProfile;
                    if (!$professionalProfile) {
                        $professionalProfile = new CliniqueProfile();
                        $professionalProfile->user_id = $user->id;
                    }
                } elseif (in_array($user->role_id, [8])) { // Pharmacie role
                    $professionalProfile = $user->pharmacieProfile;
                    if (!$professionalProfile) {
                        $professionalProfile = new PharmacieProfile();
                        $professionalProfile->user_id = $user->id;
                    }
                } elseif (in_array($user->role_id, [9])) { // Parapharmacie role
                    $professionalProfile = $user->parapharmacieProfile;
                    if (!$professionalProfile) {
                        $professionalProfile = new ParapharmacieProfile();
                        $professionalProfile->user_id = $user->id;
                    }
                } elseif (in_array($user->role_id, [10])) { // Labo analyse role
                    $professionalProfile = $user->laboAnalyseProfile;
                    if (!$professionalProfile) {
                        $professionalProfile = new LaboAnalyseProfile();
                        $professionalProfile->user_id = $user->id;
                    }
                } elseif (in_array($user->role_id, [11])) { // Centre radiologie role
                    $professionalProfile = $user->centreRadiologieProfile;
                    if (!$professionalProfile) {
                        $professionalProfile = new CentreRadiologieProfile();
                        $professionalProfile->user_id = $user->id;
                    }
                }
            }

            if ($professionalProfile) {
                // Common fields for all professional/organization profiles
                \Log::info('Profile update input snapshot', [
                    'user_id' => $user->id,
                    'table' => method_exists($professionalProfile, 'getTable') ? $professionalProfile->getTable() : 'unknown',
                    'incoming' => [
                        'specialty' => $request->specialty,
                        'presentation' => $request->presentation,
                        'additional_info' => $request->additional_info,
                        'ville' => $request->ville,
                        'adresse' => $request->address,
                        'horaire_start' => $request->horaire_start,
                        'horaire_end' => $request->horaire_end,
                        'moyens_transport' => $request->moyens_transport,
                        'moyens_paiement' => $request->moyens_paiement,
                        'jours_disponibles' => $request->jours_disponibles,
                        'informations_pratiques' => $request->informations_pratiques,
                        'contact_urgence' => $request->contact_urgence,
                    ],
                ]);
                if ($request->has('experience_years')) {
                    $professionalProfile->experience_years = $request->experience_years;
                }
                if ($request->has('specialty')) {
                    $incomingSpec = $request->specialty;
                    $specJson = null;
                    if (is_array($incomingSpec)) {
                        // If frontend sent an array, encode directly
                        $specJson = json_encode($incomingSpec, JSON_UNESCAPED_UNICODE);
                    } else {
                        $specStr = (string)$incomingSpec;
                        $trim = trim($specStr);
                        // If looks like JSON and decodes, keep as-is; otherwise wrap as JSON array
                        $looksJson = ($trim !== '' && ($trim[0] === '[' || $trim[0] === '{'));
                        $decoded = $looksJson ? json_decode($trim, true) : null;
                        if ($decoded !== null) {
                            $specJson = $trim;
                        } else {
                            // Wrap scalar specialty into JSON array, e.g., ["Kinésithérapie orthopédique"]
                            $specJson = json_encode([$specStr], JSON_UNESCAPED_UNICODE);
                        }
                    }
                    $professionalProfile->specialty = $specJson;
                }
                if ($request->has('address')) {
                    $professionalProfile->adresse = $request->address;
                }
                if ($request->has('ville')) {
                    $professionalProfile->ville = $request->ville;
                }
                if ($request->has('presentation')) {
                    $professionalProfile->presentation = $request->presentation;
                }
                if ($request->has('additional_info')) {
                    $professionalProfile->additional_info = $request->additional_info;
                }
                if ($request->has('horaire_start')) {
                    $professionalProfile->horaire_start = $request->horaire_start;
                }
                if ($request->has('horaire_end')) {
                    $professionalProfile->horaire_end = $request->horaire_end;
                }
                if ($request->has('disponible')) {
                    $professionalProfile->disponible = $request->disponible;
                }
                if ($request->has('absence_start_date')) {
                    $professionalProfile->absence_start_date = $request->absence_start_date;
                }
                if ($request->has('absence_end_date')) {
                    $professionalProfile->absence_end_date = $request->absence_end_date;
                }
                if ($request->has('carte_professionnelle')) {
                    $professionalProfile->carte_professionnelle = $request->carte_professionnelle;
                }
                if ($request->has('numero_carte_professionnelle')) {
                    $professionalProfile->numero_carte_professionnelle = $request->numero_carte_professionnelle;
                }
                // Handle diplomes/diplomas (support both column names across tables)
                $diplomesInput = null;
                if ($request->filled('diplomes')) {
                    $diplomesInput = $request->diplomes;
                } elseif ($request->filled('diplomas')) {
                    $diplomesInput = $request->diplomas;
                }
                if ($diplomesInput !== null) {
                    $tableName = $professionalProfile->getTable();
                    $hasDiplomesCol = Schema::hasColumn($tableName, 'diplomes');
                    $hasDiplomasCol = Schema::hasColumn($tableName, 'diplomas');

                    \Log::info('Diplomes update debug', [
                        'user_id' => $user->id,
                        'role_id' => $user->role_id,
                        'table' => $tableName,
                        'has_diplomes_col' => $hasDiplomesCol,
                        'has_diplomas_col' => $hasDiplomasCol,
                        'payload_len' => is_string($diplomesInput) ? strlen($diplomesInput) : null,
                    ]);

                    // Write to whichever column exists on this profile table
                    if ($hasDiplomesCol) {
                        $professionalProfile->diplomes = $diplomesInput;
                    }
                    if ($hasDiplomasCol) {
                        $professionalProfile->diplomas = $diplomesInput;
                    }
                }
                if ($request->filled('experiences')) {
                    $professionalProfile->experiences = $request->experiences;
                }
                // Guard optional columns by schema existence
                if ($request->has('rating')) {
                    $tableName = $professionalProfile->getTable();
                    if (Schema::hasColumn($tableName, 'rating')) {
                        $professionalProfile->rating = $request->rating;
                    }
                }
                if ($request->has('rdv_patients_suivis_uniquement')) {
                    $professionalProfile->rdv_patients_suivis_uniquement = $request->rdv_patients_suivis_uniquement;
                }
                if ($request->has('vacation_mode')) {
                    $tableName = $professionalProfile->getTable();
                    if (Schema::hasColumn($tableName, 'vacation_mode')) {
                        $professionalProfile->vacation_mode = $request->vacation_mode;
                    }
                }
                if ($request->filled('vacation_auto_reactivate_date')) {
                    $tableName = $professionalProfile->getTable();
                    if (Schema::hasColumn($tableName, 'vacation_auto_reactivate_date')) {
                        $professionalProfile->vacation_auto_reactivate_date = $request->vacation_auto_reactivate_date;
                    }
                }

                // Handle JSON fields
                if ($request->has('jours_disponibles')) {
                    $professionalProfile->jours_disponibles = $request->jours_disponibles;
                }
                if ($request->has('moyens_transport')) {
                    $professionalProfile->moyens_transport = $request->moyens_transport;
                }
                if ($request->has('moyens_paiement')) {
                    $professionalProfile->moyens_paiement = $request->moyens_paiement;
                }
                if ($request->has('informations_pratiques')) {
                    $professionalProfile->informations_pratiques = $request->informations_pratiques;
                }
                if ($request->has('contact_urgence')) {
                    $professionalProfile->contact_urgence = $request->contact_urgence;
                }
                if ($request->filled('nom_pharmacie')) {
                    $professionalProfile->nom_pharmacie = $request->nom_pharmacie;
                }
                if ($request->filled('nom_parapharmacie')) {
                    $professionalProfile->nom_parapharmacie = $request->nom_parapharmacie;
                }
                if ($request->filled('nom_centre')) {
                    $professionalProfile->nom_centre = $request->nom_centre;
                }
                if ($request->filled('nom_labo')) {
                    $professionalProfile->nom_labo = $request->nom_labo;
                }
                if ($request->filled('localisation')) {
                    $professionalProfile->localisation = $request->localisation;
                }
                if ($request->filled('nbr_personnel')) {
                    $professionalProfile->nbr_personnel = $request->nbr_personnel;
                }
                if ($request->filled('gerant_name')) {
                    $professionalProfile->gerant_name = $request->gerant_name;
                }
                if ($request->filled('responsable_name')) {
                    $professionalProfile->responsable_name = $request->responsable_name;
                }
                if ($request->filled('services')) {
                    $professionalProfile->services = $request->services;
                }
                if ($request->filled('etablissement_image')) {
                    $professionalProfile->etablissement_image = $request->etablissement_image;
                }
                if ($request->filled('description')) {
                    $professionalProfile->description = $request->description;
                }
                if ($request->filled('org_presentation')) {
                    $professionalProfile->org_presentation = $request->org_presentation;
                }
                if ($request->filled('services_description')) {
                    $professionalProfile->services_description = $request->services_description;
                }

                $professionalProfile->save();
                \Log::info('Profile update persisted', [
                    'user_id' => $user->id,
                    'table' => method_exists($professionalProfile, 'getTable') ? $professionalProfile->getTable() : 'unknown',
                    'profile_id' => $professionalProfile->id,
                ]);
            }

            // Load only relevant relations in response
            $profileRelations = ['profile'];
            $roleEntity = null;
            try { $roleEntity = $user->role; } catch (\Throwable $e) { $roleEntity = null; }
            $roleName = strtolower(($roleEntity->name ?? ($user->role_name ?? '')));
            if (in_array($roleName, ['medecin', 'doctor'])) {
                $profileRelations[] = 'medecinProfile';
            } elseif ($roleName === 'kine') {
                $profileRelations[] = 'kineProfile';
            } elseif ($roleName === 'orthophoniste') {
                $profileRelations[] = 'orthophonisteProfile';
            } elseif ($roleName === 'psychologue') {
                $profileRelations[] = 'psychologueProfile';
            } elseif ($roleName === 'clinique') {
                $profileRelations[] = 'cliniqueProfile';
            } elseif ($roleName === 'pharmacie') {
                $profileRelations[] = 'pharmacieProfile';
            } elseif ($roleName === 'parapharmacie') {
                $profileRelations[] = 'parapharmacieProfile';
            } elseif ($roleName === 'labo_analyse') {
                $profileRelations[] = 'laboAnalyseProfile';
            } elseif ($roleName === 'centre_radiologie') {
                $profileRelations[] = 'centreRadiologieProfile';
            }

            return response()->json([
                'message' => 'Professional profile updated successfully',
                'user' => $user->fresh()->load($profileRelations)
            ]);

        } catch (\Exception $e) {
            \Log::error('Error updating professional profile: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to update professional profile: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update professional profile avatar/image
     */
    public function updateProfileImage(Request $request)
    {
        try {
            $user = auth()->user();

            // Accept either single profile image OR gallery images
            $request->validate([
                'image' => 'nullable|image|max:5120', // 5MB max
                'imgs' => 'nullable|array',
                'imgs.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
                'imgs_paths' => 'nullable|array',
                'imgs_paths.*' => 'nullable|string',
                'delete_paths' => 'nullable|array',
                'delete_paths.*' => 'nullable|string',
            ]);

            // Helper: resolve current professional/organization profile by role
            $getProfile = function () use ($user) {
                $roleEntity = null;
                try { $roleEntity = $user->role; } catch (\Throwable $e) { $roleEntity = null; }
                $roleName = strtolower(($roleEntity->name ?? ($user->role_name ?? '')));
                if (in_array($roleName, ['medecin', 'doctor'])) return $user->medecinProfile; // Medecin
                if ($roleName === 'kine') return $user->kineProfile; // Kine
                if ($roleName === 'orthophoniste') return $user->orthophonisteProfile; // Orthophoniste
                if ($roleName === 'psychologue') return $user->psychologueProfile; // Psychologue
                if ($roleName === 'clinique') return $user->cliniqueProfile; // Clinique
                if ($roleName === 'pharmacie') return $user->pharmacieProfile; // Pharmacie
                if ($roleName === 'parapharmacie') return $user->parapharmacieProfile; // Parapharmacie
                if ($roleName === 'labo_analyse') return $user->laboAnalyseProfile; // Labo
                if ($roleName === 'centre_radiologie') return $user->centreRadiologieProfile; // Centre radiologie
                return null;
            };

            $response = [ 'message' => 'Images updated successfully' ];

            // Resolve current profile
            $professionalProfile = $getProfile();

            // Collect keep paths from request
            $keepPaths = [];
            if ($request->has('imgs_paths')) {
                $rawKeep = $request->input('imgs_paths', []);
                foreach ((array)$rawKeep as $p) {
                    if (!is_string($p)) continue;
                    $p = str_replace('\\', '/', $p);
                    if (strpos($p, '/storage/') !== 0) {
                        if (preg_match('#^/(imgs|profiles|clinic|parapharmacie|pharmacie|labo|radiologie)/#i', $p)) {
                            $p = '/storage' . $p;
                        }
                    }
                    $keepPaths[] = $p;
                }
                // De-duplicate and cap at 6
                $keepPaths = array_values(array_unique($keepPaths));
                $keepPaths = array_slice($keepPaths, 0, 6);
            }

            // Optionally delete specific files from disk
            if ($request->has('delete_paths')) {
                // Only allow deletion of images that currently belong to the authenticated user's gallery
                $currentImgs = [];
                if ($professionalProfile && $professionalProfile->imgs) {
                    try {
                        $decoded = json_decode($professionalProfile->imgs, true);
                        if (is_array($decoded)) $currentImgs = $decoded;
                    } catch (\Exception $e) {}
                }
                $currentImgs = array_map(function ($p) {
                    return '/storage/' . ltrim(str_replace(['/storage/', '\\'], ['', '/'], (string)$p), '/');
                }, $currentImgs);

                foreach ((array)$request->input('delete_paths', []) as $dp) {
                    if (!is_string($dp)) continue;
                    $dp = str_replace('\\', '/', $dp);
                    $normalizedStorage = '/storage/' . ltrim(str_replace('/storage/', '', $dp), '/');
                    if (!in_array($normalizedStorage, $currentImgs, true)) {
                        continue; // skip deleting files that are not part of user's gallery
                    }
                    $internal = ltrim(str_replace('/storage/', '', $normalizedStorage), '/');
                    if ($internal && Storage::disk('public')->exists($internal)) {
                        Storage::disk('public')->delete($internal);
                    }
                }
            }

            // Handle single profile/etablissement image
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('professional', $filename, 'public');

                $professionalProfile = $getProfile();
                if ($professionalProfile) {
                    $imageField = in_array($user->role_id, [7, 8, 10]) ? 'etablissement_image' : 'profile_image';
                    if ($professionalProfile->$imageField && Storage::disk('public')->exists(str_replace('/storage/', '', $professionalProfile->$imageField))) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $professionalProfile->$imageField));
                    }
                    $professionalProfile->$imageField = '/storage/' . $path;
                    $professionalProfile->save();
                    $response['path'] = '/storage/' . $path;
                }
            }

            // Handle gallery images update (keep existing paths + add new uploads), limit 6
            $finalPaths = null;

            // Start with keep paths if provided
            if (!empty($keepPaths)) {
                $finalPaths = $keepPaths;
            }

            // Append newly uploaded imgs if present
            if ($request->hasFile('imgs')) {
                $files = $request->file('imgs');
                $newPaths = [];
                foreach ($files as $file) {
                    $filename = time() . '_' . $file->getClientOriginalName();
                    $stored = $file->storeAs('public/imgs', $filename);
                    $newPaths[] = '/storage/' . str_replace('public/', '', $stored);
                }
                $finalPaths = array_slice(array_merge($finalPaths ?: [], $newPaths), 0, 6);
            }

            // If finalPaths set (either keep only or merged), persist
            if (is_array($finalPaths)) {
                if ($professionalProfile) {
                    $professionalProfile->imgs = json_encode($finalPaths, JSON_UNESCAPED_UNICODE);
                    $professionalProfile->save();
                    $response['imgs'] = $finalPaths;
                }
            }

            return response()->json($response);
        } catch (\Exception $e) {
            \Log::error('Error updating professional image: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to update professional image: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle professional availability
     */
    public function toggleAvailability(Request $request)
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'disponible' => 'required|boolean',
            ]);

            // Get the appropriate professional profile by role name
            $professionalProfile = null;
            $roleEntity = null;
            try { $roleEntity = $user->role; } catch (\Throwable $e) { $roleEntity = null; }
            $roleName = strtolower(($roleEntity->name ?? ($user->role_name ?? '')));
            if (in_array($roleName, ['medecin', 'doctor'])) {
                $professionalProfile = $user->medecinProfile;
            } elseif ($roleName === 'kine') {
                $professionalProfile = $user->kineProfile;
            } elseif ($roleName === 'orthophoniste') {
                $professionalProfile = $user->orthophonisteProfile;
            } elseif ($roleName === 'psychologue') {
                $professionalProfile = $user->psychologueProfile;
            } elseif ($roleName === 'clinique') {
                $professionalProfile = $user->cliniqueProfile;
            } elseif ($roleName === 'pharmacie') {
                $professionalProfile = $user->pharmacieProfile;
            } elseif ($roleName === 'parapharmacie') {
                $professionalProfile = $user->parapharmacieProfile;
            } elseif ($roleName === 'labo_analyse') {
                $professionalProfile = $user->laboAnalyseProfile;
            } elseif ($roleName === 'centre_radiologie') {
                $professionalProfile = $user->centreRadiologieProfile;
            }

            if ($professionalProfile) {
                $professionalProfile->disponible = $request->disponible;
                $professionalProfile->save();

                return response()->json([
                    'message' => 'Availability updated successfully',
                    'disponible' => $professionalProfile->disponible
                ]);
            }

            return response()->json(['error' => 'Professional profile not found'], 404);

        } catch (\Exception $e) {
            \Log::error('Error toggling availability: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to update availability: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Set absence period
     */
    public function setAbsence(Request $request)
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'absence_start_date' => 'required|date',
                'absence_end_date' => 'required|date|after:absence_start_date',
            ]);

            // Resolve professional profile by role name
            $professionalProfile = null;
            $roleName = strtolower(optional($user->role)->name ?? ($user->role_name ?? ''));
            if (in_array($roleName, ['medecin', 'doctor'])) {
                $professionalProfile = $user->medecinProfile;
            } elseif ($roleName === 'kine') {
                $professionalProfile = $user->kineProfile;
            } elseif ($roleName === 'orthophoniste') {
                $professionalProfile = $user->orthophonisteProfile;
            } elseif ($roleName === 'psychologue') {
                $professionalProfile = $user->psychologueProfile;
            }

            if ($professionalProfile) {
                $professionalProfile->absence_start_date = $request->absence_start_date;
                $professionalProfile->absence_end_date = $request->absence_end_date;
                $professionalProfile->disponible = false; // Unavailable during absence
                $professionalProfile->save();

                return response()->json([
                    'message' => 'Absence period set successfully',
                    'absence_start_date' => $professionalProfile->absence_start_date,
                    'absence_end_date' => $professionalProfile->absence_end_date
                ]);
            }

            return response()->json(['error' => 'Professional profile not found'], 404);

        } catch (\Exception $e) {
            \Log::error('Error setting absence: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to set absence: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle vacation mode - hides/shows professional from search results
     */
    public function toggleVacationMode(Request $request)
    {
        try {
            $user = auth()->user();
            
            $request->validate([
                'vacation_mode' => 'required|boolean',
                'vacation_auto_reactivate_date' => 'nullable|date',
            ]);

            // Get the appropriate professional profile by role name
            $professionalProfile = null;
            $roleName = strtolower($user->role->name ?? ($user->role_name ?? ''));
            if (in_array($roleName, ['medecin', 'doctor'])) {
                $professionalProfile = $user->medecinProfile;
            } elseif ($roleName === 'kine') {
                $professionalProfile = $user->kineProfile;
            } elseif ($roleName === 'orthophoniste') {
                $professionalProfile = $user->orthophonisteProfile;
            } elseif ($roleName === 'psychologue') {
                $professionalProfile = $user->psychologueProfile;
            } elseif ($roleName === 'clinique') {
                $professionalProfile = $user->cliniqueProfile;
            } elseif ($roleName === 'pharmacie') {
                $professionalProfile = $user->pharmacieProfile;
            } elseif ($roleName === 'parapharmacie') {
                $professionalProfile = $user->parapharmacieProfile;
            } elseif ($roleName === 'labo_analyse') {
                $professionalProfile = $user->laboAnalyseProfile;
            } elseif ($roleName === 'centre_radiologie') {
                $professionalProfile = $user->centreRadiologieProfile;
            }

            if ($professionalProfile) {
                // Use existing disponible column for vacation mode (inverse logic)
                $professionalProfile->disponible = !$request->vacation_mode;
                
                if ($request->vacation_mode) {
                    // When enabling vacation mode, set absence dates if auto-reactivate date provided
                    if ($request->filled('vacation_auto_reactivate_date')) {
                        $professionalProfile->absence_start_date = now()->toDateString();
                        $professionalProfile->absence_end_date = $request->vacation_auto_reactivate_date;
                    }
                } else {
                    // When disabling vacation mode, clear absence dates
                    $professionalProfile->absence_start_date = null;
                    $professionalProfile->absence_end_date = null;
                }
                
                $professionalProfile->save();

                $message = $request->vacation_mode 
                    ? 'Mode vacances activé. Votre profil est maintenant masqué des résultats de recherche.'
                    : 'Mode vacances désactivé. Votre profil est maintenant visible dans les résultats de recherche.';

                return response()->json([
                    'message' => $message,
                    'vacation_mode' => !$professionalProfile->disponible,
                    'vacation_auto_reactivate_date' => $professionalProfile->absence_end_date
                ]);
            }

            return response()->json(['error' => 'Professional profile not found'], 404);

        } catch (\Exception $e) {
            \Log::error('Error toggling vacation mode: ' . $e->getMessage(), [
                'user_id' => auth()->id(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => 'Failed to update vacation mode: ' . $e->getMessage()], 500);
        }
    }
}
