<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\PatientProfile;
use App\Models\MedecinProfile;
use App\Models\KineProfile;
use App\Models\OrthophonisteProfile;
use App\Models\PsychologueProfile;
use App\Models\CliniqueProfile;
use App\Models\PharmacieProfile;
use App\Models\ParapharmacieProfile;
use App\Models\LaboAnalyseProfile;
use App\Models\CentreRadiologieProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Role;

class RegisterController extends Controller
{
    public function register(Request $request)
    {
        try {
            DB::beginTransaction();
            
            // 🟢 DETAILED BACKEND DEBUG LOGGING
            \Log::info('🟢 === BACKEND REQUEST DEBUG ===');
            \Log::info('🟢 Request Method: ' . $request->method());
            \Log::info('🟢 Request URL: ' . $request->fullUrl());
            \Log::info('🟢 Content Type: ' . $request->header('Content-Type'));
            \Log::info('🟢 All Request Keys: ' . json_encode(array_keys($request->all())));
            \Log::info('🟢 Full Request Data: ' . json_encode($request->all()));
            
            \Log::info('🟢 NEW PROFILE FIELDS RECEIVED:');
            \Log::info('🟢 numero_carte_professionnelle: ' . ($request->numero_carte_professionnelle ?? 'NULL'));
            \Log::info('🟢 moyens_paiement type: ' . gettype($request->moyens_paiement) . ' value: ' . json_encode($request->moyens_paiement));
            \Log::info('🟢 moyens_transport type: ' . gettype($request->moyens_transport) . ' value: ' . json_encode($request->moyens_transport));
            \Log::info('🟢 informations_pratiques: ' . ($request->informations_pratiques ?? 'NULL'));
            \Log::info('🟢 jours_disponibles type: ' . gettype($request->jours_disponibles) . ' value: ' . json_encode($request->jours_disponibles));
            \Log::info('🟢 contact_urgence: ' . ($request->contact_urgence ?? 'NULL'));
            \Log::info('🟢 rdv_patients_suivis_uniquement: ' . ($request->rdv_patients_suivis_uniquement ?? 'NULL'));
            \Log::info('🟢 === END BACKEND REQUEST DEBUG ===');

            // Validate common fields with comprehensive validation rules
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
                // Add validation for new profile fields
                'numero_carte_professionnelle' => 'nullable|string|max:100',
                'moyens_paiement' => 'nullable|array',
                'moyens_transport' => 'nullable|array', 
                'informations_pratiques' => 'nullable|string|max:1000',
                'jours_disponibles' => 'nullable|array',
                'contact_urgence' => 'nullable|string|max:20',
                'rdv_patients_suivis_uniquement' => 'nullable|boolean'
            ], [
                'password.regex' => 'Le mot de passe doit contenir au moins 8 caractères, une lettre majuscule, une lettre minuscule, un chiffre et un caractère spécial (@$!%*?&).',
                'password.confirmed' => 'La confirmation du mot de passe ne correspond pas.',
                'email.unique' => 'Cette adresse email est déjà utilisée.',
                'phone.unique' => 'Ce numéro de téléphone est déjà utilisé.'
            ]);

            // Create user - only with basic required fields
            $userData = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => bcrypt($validated['password']),
                'phone' => $validated['phone'],
                'role_id' => $validated['role_id'],
                'is_verified' => 0,
                'is_subscribed' => 0
            ];

            // Add optional fields only if they exist in users table
            if (Schema::hasColumn('users', 'presentation') && $request->presentation) {
                $userData['presentation'] = $request->presentation;
            }
            if (Schema::hasColumn('users', 'carte_professionnelle') && $request->hasFile('carte_professionnelle')) {
                $userData['carte_professionnelle'] = $request->file('carte_professionnelle')->store('public/cartes_professionnelles');
            }

            \Log::info('Creating user with data:', $userData);
            $user = User::create($userData);

            $role = Role::find($validated['role_id']);

            if (!$role) {
                throw new \Exception('Role not found');
            }

            // Handle profile creation based on role
            switch ($role->name) {
                case 'patient':
                    \Log::info('Creating patient profile for user:', ['user_id' => $user->id]);
                    
                    try {
                        $profileData = [
                            'user_id' => $user->id,
                            'missed_rdv' => 0
                        ];

                        // Add optional fields only if they exist and have values
                        if ($request->age) {
                            $profileData['age'] = $request->age;
                        }
                        if ($request->gender) {
                            $profileData['gender'] = $request->gender;
                        }
                        if ($request->blood_type) {
                            $profileData['blood_type'] = $request->blood_type;
                        }
                        if ($request->allergies) {
                            $profileData['allergies'] = is_array($request->allergies) ? json_encode($request->allergies) : $request->allergies;
                        }
                        if ($request->chronic_diseases) {
                            $profileData['chronic_diseases'] = is_array($request->chronic_diseases) ? json_encode($request->chronic_diseases) : $request->chronic_diseases;
                        }

                        \Log::info('Patient profile data to create:', $profileData);
                        
                        $profile = PatientProfile::create($profileData);
                        \Log::info('Patient profile created successfully:', ['profile_id' => $profile->id]);
                        
                    } catch (\Exception $e) {
                        \Log::error('Error creating patient profile:', [
                            'error' => $e->getMessage(),
                            'line' => $e->getLine(),
                            'file' => $e->getFile()
                        ]);
                        throw $e;
                    }
                    break;

                case 'medecin':
                    \Log::info('Creating medecin profile for user:', ['user_id' => $user->id]);
                    
                    try {
                        // Process specialty data for medecin
                        $specialtyData = [];
                        if ($request->has('specialty')) {
                            $specialtyData = is_array($request->specialty) ? $request->specialty : json_decode($request->specialty, true);
                        }

                        // Process diplomes data
                        $diplomesData = [];
                        if ($request->has('diplomes')) {
                            $diplomesData = is_array($request->diplomes) ? $request->diplomes : json_decode($request->diplomes, true);
                        }

                        // Process experiences data
                        $experiencesData = [];
                        if ($request->has('experiences')) {
                            $experiencesData = is_array($request->experiences) ? $request->experiences : json_decode($request->experiences, true);
                        }

                        $profileData = [
                            'user_id' => $user->id,
                            'specialty' => json_encode($specialtyData, JSON_UNESCAPED_UNICODE),
                            'experience_years' => $request->experience_years,
                            'adresse' => $request->adresse,
                            'ville' => $request->ville,
                            'presentation' => $request->presentation,
                            'disponible' => true
                        ];

                        // Add time fields directly since they exist in the table
                        $profileData['horaire_start'] = $request->horaire_start;
                        $profileData['horaire_end'] = $request->horaire_end;
                        $profileData['additional_info'] = $request->additional_info;

                        // Add optional fields only if they exist in the table
                        if (Schema::hasColumn('medecin_profiles', 'diplomes')) {
                            $profileData['diplomes'] = json_encode($diplomesData, JSON_UNESCAPED_UNICODE);
                        }
                        if (Schema::hasColumn('medecin_profiles', 'experiences')) {
                            $profileData['experiences'] = json_encode($experiencesData, JSON_UNESCAPED_UNICODE);
                        }
                        if (Schema::hasColumn('medecin_profiles', 'carte_professionnelle')) {
                            $profileData['carte_professionnelle'] = $request->hasFile('carte_professionnelle') ? 
                                $request->file('carte_professionnelle')->store('public/cartes_professionnelles') : null;
                        }
                        if (Schema::hasColumn('medecin_profiles', 'profile_image')) {
                            $profileData['profile_image'] = $request->hasFile('profile_image') ? 
                                $request->file('profile_image')->store('public/profiles') : null;
                        }

                        // Add new profile fields
                        if (Schema::hasColumn('medecin_profiles', 'moyens_paiement')) {
                            $profileData['moyens_paiement'] = $request->moyens_paiement ? json_encode($request->moyens_paiement, JSON_UNESCAPED_UNICODE) : null;
                        }
                        if (Schema::hasColumn('medecin_profiles', 'moyens_transport')) {
                            $profileData['moyens_transport'] = $request->moyens_transport ? json_encode($request->moyens_transport, JSON_UNESCAPED_UNICODE) : null;
                        }
                        if (Schema::hasColumn('medecin_profiles', 'informations_pratiques')) {
                            $profileData['informations_pratiques'] = $request->informations_pratiques;
                        }
                        if (Schema::hasColumn('medecin_profiles', 'jours_disponibles')) {
                            $profileData['jours_disponibles'] = $request->jours_disponibles ? json_encode($request->jours_disponibles, JSON_UNESCAPED_UNICODE) : null;
                        }
                        if (Schema::hasColumn('medecin_profiles', 'contact_urgence')) {
                            $profileData['contact_urgence'] = $request->contact_urgence;
                        }
                        if (Schema::hasColumn('medecin_profiles', 'rdv_patients_suivis_uniquement')) {
                            $profileData['rdv_patients_suivis_uniquement'] = $request->rdv_patients_suivis_uniquement ? 1 : 0;
                        }
                        if (Schema::hasColumn('medecin_profiles', 'numero_carte_professionnelle')) {
                            $profileData['numero_carte_professionnelle'] = $request->numero_carte_professionnelle;
                        }

                        \Log::info('🟢 === BACKEND PROFILE DATA DEBUG ===');
                        \Log::info('🟢 Profile data to create: ' . json_encode($profileData));
                        \Log::info('🟢 NEW FIELDS IN PROFILE DATA:');
                        \Log::info('🟢 moyens_paiement in profileData: ' . ($profileData['moyens_paiement'] ?? 'NOT SET'));
                        \Log::info('🟢 moyens_transport in profileData: ' . ($profileData['moyens_transport'] ?? 'NOT SET'));
                        \Log::info('🟢 informations_pratiques in profileData: ' . ($profileData['informations_pratiques'] ?? 'NOT SET'));
                        \Log::info('🟢 jours_disponibles in profileData: ' . ($profileData['jours_disponibles'] ?? 'NOT SET'));
                        \Log::info('🟢 contact_urgence in profileData: ' . ($profileData['contact_urgence'] ?? 'NOT SET'));
                        \Log::info('🟢 rdv_patients_suivis_uniquement in profileData: ' . ($profileData['rdv_patients_suivis_uniquement'] ?? 'NOT SET'));
                        \Log::info('🟢 numero_carte_professionnelle in profileData: ' . ($profileData['numero_carte_professionnelle'] ?? 'NOT SET'));
                        \Log::info('🟢 === END BACKEND PROFILE DATA DEBUG ===');
                        \Log::info('Request rdv_patients_suivis_uniquement: ' . ($request->rdv_patients_suivis_uniquement ?? 'null'));
                        \Log::info('Request numero_carte_professionnelle: ' . ($request->numero_carte_professionnelle ?? 'null'));
                        
                        $profile = MedecinProfile::create($profileData);
                        \Log::info('Medecin profile created successfully:', ['profile_id' => $profile->id]);
                        
                    } catch (\Exception $e) {
                        \Log::error('Error creating medecin profile:', [
                            'error' => $e->getMessage(),
                            'line' => $e->getLine(),
                            'file' => $e->getFile()
                        ]);
                        throw $e;
                    }
                    break;

                case 'kine':
                case 'orthophoniste':
                case 'psychologue':
                    // Process specialty data
                    $specialtyData = [];
                    if ($request->has('specialty')) {
                        $specialtyData = is_array($request->specialty) ? $request->specialty : json_decode($request->specialty, true);
                        
                        // Check for "Autres" option and replace with custom specialty
                        if (is_array($specialtyData) && in_array("Autres", $specialtyData) && $request->has('other_specialty')) {
                            $specialtyData = array_filter($specialtyData, function($item) {
                                return $item !== "Autres";
                            });
                            $specialtyData[] = $request->other_specialty;
                        }
                    }

                    // Process diplomes data
                    $diplomesData = [];
                    if ($request->has('diplomes')) {
                        $diplomesData = is_array($request->diplomes) ? $request->diplomes : json_decode($request->diplomes, true);
                    }

                    // Process experiences data
                    $experiencesData = [];
                    if ($request->has('experiences')) {
                        $experiencesData = is_array($request->experiences) ? $request->experiences : json_decode($request->experiences, true);
                    }

                    // Get the appropriate model class
                    $modelClass = 'App\\Models\\' . ucfirst($role->name) . 'Profile';

                    // Create the profile with complete data
                    $modelClass::create([
                        'user_id' => $user->id,
                        'specialty' => json_encode($specialtyData, JSON_UNESCAPED_UNICODE),
                        'experience_years' => $request->experience_years,
                        'horaire_start' => $request->horaire_start,
                        'horaire_end' => $request->horaire_end,
                        'diplomes' => json_encode($diplomesData, JSON_UNESCAPED_UNICODE),
                        'experiences' => json_encode($experiencesData, JSON_UNESCAPED_UNICODE),
                        'adresse' => $request->adresse,
                        'ville' => $request->ville,
                        'presentation' => $request->presentation,
                        'additional_info' => $request->additional_info,
                        'carte_professionnelle' => $request->hasFile('carte_professionnelle') ? 
                            $request->file('carte_professionnelle')->store('public/cartes_professionnelles') : null,
                        'profile_image' => $request->hasFile('profile_image') ? 
                            $request->file('profile_image')->store('public/profiles') : null,
                        'disponible' => true
                    ]);
                    break;

                case 'clinique':
                    // Log incoming data for debugging
                    \Log::info('Clinic registration data:', [
                        'ville' => $request->ville,
                        'responsable_name' => $request->responsable_name,
                        'description' => $request->description,
                        'org_presentation' => $request->org_presentation,
                        'services_description' => $request->services_description,
                        'additional_info' => $request->additional_info,
                        'all_request' => $request->all()
                    ]);

                    // Handle etablissement image upload
                    $etablissementImagePath = null;
                    if ($request->hasFile('etablissement_image')) {
                        $file = $request->file('etablissement_image');
                        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $path = $file->storeAs('public/etablissements', $filename);
                        $etablissementImagePath = str_replace('public/', '', $path);
                    }

                    // Process services data
                    $servicesData = [];
                    if ($request->has('services')) {
                        $servicesData = json_decode($request->services, true);
                        
                        // Handle "Autres" option with custom service
                        if (is_array($servicesData) && in_array("Autres", $servicesData) && $request->has('other_service')) {
                            // Remove "Autres" from the array
                            $servicesData = array_filter($servicesData, function($item) {
                                return $item !== "Autres";
                            });
                            // Add the custom service
                            $servicesData[] = $request->other_service;
                        }
                    }

                    $clinicData = [
                        'user_id' => $user->id,
                        'nom_clinique' => $request->nom_etablissement,
                        'adresse' => $request->adresse,
                        'ville' => $request->ville,
                        'horaires' => json_encode([
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ]),
                        'responsable_name' => $request->responsable_name,
                        'services' => json_encode($servicesData),
                        'etablissement_image' => $etablissementImagePath,
                        'rating' => 0.0,
                        'description' => $request->description,
                        'org_presentation' => $request->org_presentation,
                        'services_description' => $request->services_description,
                        'additional_info' => $request->additional_info,
                        'disponible' => true
                    ];

                    \Log::info('Clinic data to be saved:', $clinicData);
                    
                    $clinic = CliniqueProfile::create($clinicData);
                    \Log::info('Clinic created with ID:', ['id' => $clinic->id]);
                    break;

                case 'pharmacie':
                case 'parapharmacie':
                case 'labo_analyse':
                case 'centre_radiologie':
                    $modelClass = 'App\\Models\\' . str_replace('_', '', ucfirst($role->name)) . 'Profile';

                    // Handle etablissement image upload
                    $etablissementImagePath = null;
                    if ($request->hasFile('etablissement_image')) {
                        $file = $request->file('etablissement_image');
                        $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                        $path = $file->storeAs('public/etablissements', $filename);
                        $etablissementImagePath = str_replace('public/', '', $path);
                    }

                    // Process services data
                    $servicesData = [];
                    if ($request->has('services')) {
                        $servicesData = json_decode($request->services, true);
                        
                        // Handle "Autres" option with custom service
                        if (is_array($servicesData) && in_array("Autres", $servicesData) && $request->has('other_service')) {
                            // Remove "Autres" from the array
                            $servicesData = array_filter($servicesData, function($item) {
                                return $item !== "Autres";
                            });
                            // Add the custom service
                            $servicesData[] = $request->other_service;
                        }
                    }

                    $profileData = [
                        'user_id' => $user->id,
                        'adresse' => $request->adresse,
                        'ville' => $request->ville,
                        'etablissement_image' => $etablissementImagePath,
                        'rating' => 0.0,
                        'description' => $request->description,
                        'org_presentation' => $request->org_presentation,
                        'services_description' => $request->services_description,
                        'additional_info' => $request->additional_info,
                        'disponible' => true
                    ];

                    // Handle organization-specific fields
                    if ($role->name === 'labo_analyse') {
                        $profileData['nom_labo'] = $request->nom_etablissement;
                        $profileData['responsable_name'] = $request->responsable_name;
                        $profileData['services'] = $servicesData; // REMOVE json_encode
                        $profileData['horaires'] = json_encode([
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ]);
                    } elseif ($role->name === 'centre_radiologie') {
                        $profileData['nom_centre'] = $request->nom_etablissement;
                        $profileData['responsable_name'] = $request->responsable_name;
                        $profileData['services'] = $servicesData; // REMOVE json_encode
                        $profileData['horaires'] = json_encode([
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ]);
                    } elseif ($role->name === 'pharmacie') {
                        $profileData['nom_pharmacie'] = $request->nom_etablissement;
                        $profileData['responsable_name'] = $request->responsable_name;
                        $profileData['horaires'] = json_encode([
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ]);
                    } elseif ($role->name === 'parapharmacie') {
                        $profileData['nom_parapharmacie'] = $request->nom_etablissement;
                        $profileData['responsable_name'] = $request->responsable_name;
                        $profileData['horaires'] = json_encode([
                            'start' => $request->horaire_start,
                            'end' => $request->horaire_end
                        ]);
                    }

                    $modelClass::create($profileData);
                    break;
            }


            DB::commit();

            // Generate token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'message' => 'Registration successful',
                'token' => $token,
                'user' => $user
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Registration error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            \Log::error('Request data: ' . json_encode($request->all()));

            if (isset($user)) {
                $user->delete();
            }

            return response()->json([
                'message' => 'Registration failed',
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => basename($e->getFile()),
                'debug' => $request->all() // Remove this in production
            ], 500);
        }
    }
}
