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

class ProfessionalProfileController extends Controller
{
    /**
     * Get professional profile data
     */
    public function getProfile()
    {
        try {
            $user = auth()->user();
            
            // Load all profile types based on user role
            $profileRelations = ['profile'];
            
            // Add professional profiles based on role
            if (in_array($user->role_id, [2, 4])) { // Medecin role
                $profileRelations[] = 'medecinProfile';
            }
            if (in_array($user->role_id, [3])) { // Kine role
                $profileRelations[] = 'kineProfile';
            }
            if (in_array($user->role_id, [5])) { // Orthophoniste role
                $profileRelations[] = 'orthophonisteProfile';
            }
            if (in_array($user->role_id, [6])) { // Psychologue role
                $profileRelations[] = 'psychologueProfile';
            }
            if (in_array($user->role_id, [7])) { // Clinique role
                $profileRelations[] = 'cliniqueProfile';
            }
            if (in_array($user->role_id, [8])) { // Pharmacie role
                $profileRelations[] = 'pharmacieProfile';
            }
            if (in_array($user->role_id, [9])) { // Parapharmacie role
                $profileRelations[] = 'parapharmacieProfile';
            }
            if (in_array($user->role_id, [10])) { // Labo analyse role
                $profileRelations[] = 'laboAnalyseProfile';
            }
            if (in_array($user->role_id, [11])) { // Centre radiologie role
                $profileRelations[] = 'centreRadiologieProfile';
            }
            
            return response()->json($user->load($profileRelations));
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
            
            // Individual Professional Profiles
            if (in_array($user->role_id, [2, 4])) { // Medecin role
                $professionalProfile = $user->medecinProfile;
                if (!$professionalProfile) {
                    $professionalProfile = new MedecinProfile();
                    $professionalProfile->user_id = $user->id;
                }
            } elseif (in_array($user->role_id, [3])) { // Kine role
                $professionalProfile = $user->kineProfile;
                if (!$professionalProfile) {
                    $professionalProfile = new KineProfile();
                    $professionalProfile->user_id = $user->id;
                }
            } elseif (in_array($user->role_id, [5])) { // Orthophoniste role
                $professionalProfile = $user->orthophonisteProfile;
                if (!$professionalProfile) {
                    $professionalProfile = new OrthophonisteProfile();
                    $professionalProfile->user_id = $user->id;
                }
            } elseif (in_array($user->role_id, [6])) { // Psychologue role
                $professionalProfile = $user->psychologueProfile;
                if (!$professionalProfile) {
                    $professionalProfile = new PsychologueProfile();
                    $professionalProfile->user_id = $user->id;
                }
            }
            // Organization Profiles
            elseif (in_array($user->role_id, [7])) { // Clinique role
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

            if ($professionalProfile) {
                // Common fields for all professional/organization profiles
                if ($request->filled('experience_years')) {
                    $professionalProfile->experience_years = $request->experience_years;
                }
                if ($request->filled('specialty')) {
                    $professionalProfile->specialty = $request->specialty;
                }
                if ($request->filled('address')) {
                    $professionalProfile->adresse = $request->address;
                }
                if ($request->filled('ville')) {
                    $professionalProfile->ville = $request->ville;
                }
                if ($request->filled('presentation')) {
                    $professionalProfile->presentation = $request->presentation;
                }
                if ($request->filled('additional_info')) {
                    $professionalProfile->additional_info = $request->additional_info;
                }
                if ($request->filled('horaire_start')) {
                    $professionalProfile->horaire_start = $request->horaire_start;
                }
                if ($request->filled('horaire_end')) {
                    $professionalProfile->horaire_end = $request->horaire_end;
                }
                if ($request->has('disponible')) {
                    $professionalProfile->disponible = $request->disponible;
                }
                if ($request->filled('absence_start_date')) {
                    $professionalProfile->absence_start_date = $request->absence_start_date;
                }
                if ($request->filled('absence_end_date')) {
                    $professionalProfile->absence_end_date = $request->absence_end_date;
                }
                if ($request->filled('carte_professionnelle')) {
                    $professionalProfile->carte_professionnelle = $request->carte_professionnelle;
                }
                if ($request->filled('numero_carte_professionnelle')) {
                    $professionalProfile->numero_carte_professionnelle = $request->numero_carte_professionnelle;
                }
                if ($request->filled('diplomes')) {
                    $professionalProfile->diplomes = $request->diplomes;
                }
                if ($request->filled('experiences')) {
                    $professionalProfile->experiences = $request->experiences;
                }
                if ($request->has('rating')) {
                    $professionalProfile->rating = $request->rating;
                }
                if ($request->has('rdv_patients_suivis_uniquement')) {
                    $professionalProfile->rdv_patients_suivis_uniquement = $request->rdv_patients_suivis_uniquement;
                }
                if ($request->has('vacation_mode')) {
                    $professionalProfile->vacation_mode = $request->vacation_mode;
                }
                if ($request->filled('vacation_auto_reactivate_date')) {
                    $professionalProfile->vacation_auto_reactivate_date = $request->vacation_auto_reactivate_date;
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
                if ($request->filled('informations_pratiques')) {
                    $professionalProfile->informations_pratiques = $request->informations_pratiques;
                }
                if ($request->filled('contact_urgence')) {
                    $professionalProfile->contact_urgence = $request->contact_urgence;
                }

                // Organization-specific fields
                if ($request->filled('nom_clinique')) {
                    $professionalProfile->nom_clinique = $request->nom_clinique;
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
            }

            // Load appropriate profiles for response
            $profileRelations = ['profile'];
            if (in_array($user->role_id, [2, 4])) {
                $profileRelations[] = 'medecinProfile';
            }
            if (in_array($user->role_id, [3])) {
                $profileRelations[] = 'kineProfile';
            }
            if (in_array($user->role_id, [5])) {
                $profileRelations[] = 'orthophonisteProfile';
            }
            if (in_array($user->role_id, [6])) {
                $profileRelations[] = 'psychologueProfile';
            }
            if (in_array($user->role_id, [7])) {
                $profileRelations[] = 'cliniqueProfile';
            }
            if (in_array($user->role_id, [8])) {
                $profileRelations[] = 'pharmacieProfile';
            }
            if (in_array($user->role_id, [9])) {
                $profileRelations[] = 'parapharmacieProfile';
            }
            if (in_array($user->role_id, [10])) {
                $profileRelations[] = 'laboAnalyseProfile';
            }
            if (in_array($user->role_id, [11])) {
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

            $request->validate([
                'image' => 'required|image|max:5120', // 5MB max
            ]);

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . $file->getClientOriginalName();

                // Store in public/uploads/professional folder
                $path = $file->storeAs('professional', $filename, 'public');

                // Get the appropriate professional profile
                $professionalProfile = null;
                
                if (in_array($user->role_id, [2, 4])) {
                    $professionalProfile = $user->medecinProfile;
                } elseif (in_array($user->role_id, [3])) {
                    $professionalProfile = $user->kineProfile;
                } elseif (in_array($user->role_id, [5])) {
                    $professionalProfile = $user->orthophonisteProfile;
                } elseif (in_array($user->role_id, [6])) {
                    $professionalProfile = $user->psychologueProfile;
                } elseif (in_array($user->role_id, [7])) {
                    $professionalProfile = $user->cliniqueProfile;
                } elseif (in_array($user->role_id, [8])) {
                    $professionalProfile = $user->pharmacieProfile;
                } elseif (in_array($user->role_id, [9])) {
                    $professionalProfile = $user->parapharmacieProfile;
                } elseif (in_array($user->role_id, [10])) {
                    $professionalProfile = $user->laboAnalyseProfile;
                } elseif (in_array($user->role_id, [11])) {
                    $professionalProfile = $user->centreRadiologieProfile;
                }

                if ($professionalProfile) {
                    // Delete old image if exists
                    $imageField = in_array($user->role_id, [7, 8, 10]) ? 'etablissement_image' : 'profile_image';
                    
                    if ($professionalProfile->$imageField && Storage::disk('public')->exists(str_replace('/storage/', '', $professionalProfile->$imageField))) {
                        Storage::disk('public')->delete(str_replace('/storage/', '', $professionalProfile->$imageField));
                    }

                    // Update profile image path
                    $professionalProfile->$imageField = '/storage/' . $path;
                    $professionalProfile->save();

                    return response()->json([
                        'message' => 'Professional image updated successfully',
                        'path' => '/storage/' . $path
                    ]);
                }
            }

            return response()->json(['message' => 'No file uploaded or invalid profile type'], 400);

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

            // Get the appropriate professional profile
            $professionalProfile = null;
            
            if (in_array($user->role_id, [2, 4])) {
                $professionalProfile = $user->medecinProfile;
            } elseif (in_array($user->role_id, [3])) {
                $professionalProfile = $user->kineProfile;
            } elseif (in_array($user->role_id, [5])) {
                $professionalProfile = $user->orthophonisteProfile;
            } elseif (in_array($user->role_id, [6])) {
                $professionalProfile = $user->psychologueProfile;
            } elseif (in_array($user->role_id, [7])) {
                $professionalProfile = $user->cliniqueProfile;
            } elseif (in_array($user->role_id, [8])) {
                $professionalProfile = $user->pharmacieProfile;
            } elseif (in_array($user->role_id, [9])) {
                $professionalProfile = $user->parapharmacieProfile;
            } elseif (in_array($user->role_id, [10])) {
                $professionalProfile = $user->laboAnalyseProfile;
            } elseif (in_array($user->role_id, [11])) {
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

            // Get the appropriate professional profile
            $professionalProfile = null;
            
            if (in_array($user->role_id, [2, 4])) {
                $professionalProfile = $user->medecinProfile;
            } elseif (in_array($user->role_id, [3])) {
                $professionalProfile = $user->kineProfile;
            } elseif (in_array($user->role_id, [5])) {
                $professionalProfile = $user->orthophonisteProfile;
            } elseif (in_array($user->role_id, [6])) {
                $professionalProfile = $user->psychologueProfile;
            } elseif (in_array($user->role_id, [7])) {
                $professionalProfile = $user->cliniqueProfile;
            } elseif (in_array($user->role_id, [8])) {
                $professionalProfile = $user->pharmacieProfile;
            } elseif (in_array($user->role_id, [9])) {
                $professionalProfile = $user->parapharmacieProfile;
            } elseif (in_array($user->role_id, [10])) {
                $professionalProfile = $user->laboAnalyseProfile;
            } elseif (in_array($user->role_id, [11])) {
                $professionalProfile = $user->centreRadiologieProfile;
            }

            if ($professionalProfile) {
                $professionalProfile->absence_start_date = $request->absence_start_date;
                $professionalProfile->absence_end_date = $request->absence_end_date;
                $professionalProfile->disponible = false; // Set as unavailable during absence
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

            // Get the appropriate professional profile
            $professionalProfile = null;
            
            if (in_array($user->role_id, [2, 4])) {
                $professionalProfile = $user->medecinProfile;
            } elseif (in_array($user->role_id, [3])) {
                $professionalProfile = $user->kineProfile;
            } elseif (in_array($user->role_id, [5])) {
                $professionalProfile = $user->orthophonisteProfile;
            } elseif (in_array($user->role_id, [6])) {
                $professionalProfile = $user->psychologueProfile;
            } elseif (in_array($user->role_id, [7])) {
                $professionalProfile = $user->cliniqueProfile;
            } elseif (in_array($user->role_id, [8])) {
                $professionalProfile = $user->pharmacieProfile;
            } elseif (in_array($user->role_id, [9])) {
                $professionalProfile = $user->parapharmacieProfile;
            } elseif (in_array($user->role_id, [10])) {
                $professionalProfile = $user->laboAnalyseProfile;
            } elseif (in_array($user->role_id, [11])) {
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
