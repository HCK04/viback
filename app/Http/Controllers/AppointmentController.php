<?php

namespace App\Http\Controllers;

use App\Models\Rdv;
use App\Models\User;
use App\Models\MedecinProfile;
use App\Models\Annonce;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentUpdated;
use App\Notifications\AppointmentCancelled;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    /**
     * Generate a unique 16-character alphanumeric appointment ID
     */
    private function generateAppointmentId()
    {
        do {
            $id = strtoupper(Str::random(16));
        } while (Rdv::where('id', $id)->exists());
        
        return $id;
    }

    /**
     * Resolve the display name for an organization by checking its profile table
     */
    private function getOrganizationDisplayName($userId, $role)
    {
        try {
            $role = strtolower((string) $role);
            $map = [
                'clinique' => ['table' => 'clinique_profiles', 'field' => 'nom_clinique'],
                'pharmacie' => ['table' => 'pharmacie_profiles', 'field' => 'nom_pharmacie'],
                'parapharmacie' => ['table' => 'parapharmacie_profiles', 'field' => 'nom_parapharmacie'],
                'centre_radiologie' => ['table' => 'centre_radiologie_profiles', 'field' => 'nom_centre'],
                'labo_analyse' => ['table' => 'labo_analyse_profiles', 'field' => 'nom_labo'],
            ];

            $candidates = [];
            if (isset($map[$role])) {
                $candidates[] = $map[$role];
            } else {
                $candidates = array_values($map);
            }

            foreach ($candidates as $cfg) {
                $name = DB::table($cfg['table'])->where('user_id', $userId)->value($cfg['field']);
                if (!empty($name)) {
                    return $name;
                }
            }

            // Fallback to users.name
            $fallback = DB::table('users')->where('id', $userId)->value('name');
            return $fallback ?: null;
        } catch (\Exception $e) {
            \Log::warning("getOrganizationDisplayName failed for user_id={$userId}, role={$role}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * List current user's appointments
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $rdvs = Rdv::with(['target' => function($query) {
                    $query->select('id', 'name');
                }])
                ->where(function($query) use ($user) {
                    $query->where('patient_id', $user->id)
                          ->orWhere('patient_email', $user->email);
                })
                ->orderBy('date_time', 'desc')
                ->get();

            // Normalize for frontend
            $data = $rdvs->map(function ($a) {
                $role = strtolower($a->target_role ?? '');
                // Determine if appointment targets an organization by role
                $orgRoles = ['clinique','pharmacie','parapharmacie','labo_analyse','centre_radiologie','organization'];
                $isOrgByRole = in_array($role, $orgRoles, true);
                // Try to resolve establishment name irrespective of stored role
                $resolved = $this->getOrganizationDisplayName($a->target_user_id, $role);
                $providerName = $resolved ?: ($a->target?->name ?? 'Établissement');

                return [
                    'id' => $a->id,
                    'doctor_id' => $a->target_user_id,
                    'doctor_name' => $providerName,
                    'provider_type' => $a->target_role,
                    'date' => $a->date_time?->format('Y-m-d'),
                    'time' => $a->date_time?->format('H:i'),
                    'status' => $a->status,
                    'reason' => $a->reason,
                    'patient_name' => $a->patient_name,
                    'is_organization_appointment' => ($isOrgByRole || !empty($resolved)),
                ];
            });

            return response()->json($data);
            
        } catch (\Exception $e) {
            \Log::error("Failed to fetch patient appointments: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des rendez-vous: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show a specific appointment
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json(['message' => 'Utilisateur non authentifié'], 401);
            }

            $rdv = Rdv::with(['target' => function($query) {
                    $query->select('id', 'name');
                }])
                ->where('id', $id)
                ->where('patient_id', $user->id)
                ->first();

            if (!$rdv) {
                return response()->json(['message' => 'Rendez-vous non trouvé'], 404);
            }

            // Get announcement data if exists
            $annonce = null;
            $price = null; // No default price
            
            // Check if RDV has annonce_id column and value
            if (isset($rdv->annonce_id) && $rdv->annonce_id) {
                $annonce = Annonce::find($rdv->annonce_id);
                if ($annonce) {
                    $price = $annonce->price;
                }
            }
            
            // Debug logging for patient appointment
            \Log::info('Patient Appointment show data:', [
                'rdv_id' => $rdv->id,
                'annonce_id' => $rdv->annonce_id ?? 'null',
                'annonce_found' => $annonce ? 'yes' : 'no',
                'price' => $price
            ]);

            // Resolve provider display name (prefer establishment name for org roles)
            $role = strtolower($rdv->target_role ?? '');
            $resolved = $this->getOrganizationDisplayName($rdv->target_user_id, $role);
            $providerName = $resolved ?: ($rdv->target ? $rdv->target->name : 'Médecin inconnu');

            // Format the appointment data
            $appointment = [
                'id' => $rdv->id,
                'doctor_name' => $providerName,
                'date' => $rdv->date_time ? Carbon::parse($rdv->date_time)->format('Y-m-d') : null,
                'time' => $rdv->date_time ? Carbon::parse($rdv->date_time)->format('H:i') : null,
                'date_time' => $rdv->date_time,
                'reason' => $rdv->reason ?? 'Consultation',
                'status' => $rdv->status ?? 'scheduled',
                'patient_id' => $rdv->patient_id,
                'patient_name' => $rdv->patient_name ?? $user->name,
                'patient_phone' => $rdv->patient_phone ?? $user->phone ?? null,
                'patient_email' => $rdv->patient_email ?? $user->email ?? null,
                'target_user_id' => $rdv->target_user_id,
                'target_role' => $rdv->target_role,
                'target_id' => $rdv->target_id,
                'annonce' => $annonce ? [
                    'id' => $annonce->id,
                    'title' => $annonce->title,
                    'description' => $annonce->description,
                    'price' => $annonce->price ?? 0,
                    'pourcentage_reduction' => $annonce->pourcentage_reduction ?? 0,
                    'address' => $annonce->address ?? '',
                ] : null,
                'price' => $price,
                'created_at' => $rdv->created_at,
                'updated_at' => $rdv->updated_at
            ];

            return response()->json($appointment);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du rendez-vous: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Book a new appointment (doctor or organization)
     */
    public function store(Request $request)
    {
        // Check if this is a doctor appointment or organization appointment
        if ($request->has('target_user_id') && $request->has('target_role')) {
            return $this->storeDoctorAppointment($request);
        } else {
            return $this->storeOrganizationAppointment($request);
        }
    }

    /**
     * Book a new appointment with doctor
     */
    private function storeDoctorAppointment(Request $request)
    {
        $user = $request->user();
        
        $validator = Validator::make($request->all(), [
            'target_user_id' => 'required|exists:users,id',
            'target_role' => 'required|string|in:doctor,medecin,kine,orthophoniste,psychologue,clinique,pharmacie,parapharmacie,labo_analyse,centre_radiologie',
            'date_time' => 'required|string',
            'reason' => 'required|string|max:500',
            'patient_name' => 'nullable|string|max:255',
            'patient_phone' => 'nullable|string|max:20',
            'patient_email' => 'nullable|email|max:255',
            'announcement_id' => 'nullable|exists:annonces,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if announcement is provided and validate if it's still active
        if ($request->has('announcement_id') && $request->announcement_id) {
            $annonce = Annonce::find($request->announcement_id);
            
            if (!$annonce) {
                return response()->json([
                    'success' => false,
                    'message' => 'Annonce introuvable'
                ], 404);
            }
            
            if (!$annonce->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Annonce désactivée ou indisponible par le propriétaire'
                ], 400);
            }
        }

        $doctor = User::findOrFail($request->target_user_id);

        // Check if professional is available (not in vacation mode)
        $professionalProfile = null;
        $roleMap = [
            'medecin' => 'medecinProfile',
            'kine' => 'kineProfile', 
            'orthophoniste' => 'orthophonisteProfile',
            'psychologue' => 'psychologueProfile'
        ];
        
        if (isset($roleMap[$request->target_role])) {
            $doctor->load($roleMap[$request->target_role]);
            $professionalProfile = $doctor->{$roleMap[$request->target_role]};
        }
        
        if ($professionalProfile && $professionalProfile->disponible === false) {
            return response()->json([
                'success' => false,
                'message' => 'Ce professionnel est actuellement en vacances et n\'accepte pas de nouveaux rendez-vous.'
            ], 400);
        }

        // Parse date_time with multiple possible formats
        try {
            // Try multiple formats: with seconds or without
            $dateTime = null;
            $formats = ['Y-m-d H:i:s', 'Y-m-d H:i'];
            
            foreach ($formats as $format) {
                try {
                    $dateTime = Carbon::createFromFormat($format, $request->date_time);
                    if ($dateTime) {
                        break;
                    }
                } catch (\Exception $formatException) {
                    continue;
                }
            }
            
            if (!$dateTime) {
                throw new \Exception('Invalid date format');
            }
        } catch (\Exception $e) {
            \Log::error('Date parsing error: ' . $e->getMessage() . ' for input: ' . $request->date_time);
            return response()->json(['message' => 'Format de date invalide: ' . $request->date_time], 422);
        }

        // Check if appointment slot is still available
        $existingAppointment = Rdv::where('target_user_id', $doctor->id)
            ->where('date_time', $dateTime)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingAppointment) {
            return response()->json(['message' => 'Ce créneau n\'est plus disponible'], 409);
        }

        // Check if appointment is in the future
        $now = now();
        $minimumTime = $now->copy()->addMinutes(30);
        
        if ($dateTime <= $minimumTime) {
            return response()->json(['message' => 'Veuillez sélectionner une date et heure au moins 30 minutes dans le futur'], 400);
        }

        try {
            $rdv = new Rdv();
            // Let database auto-increment the ID instead of generating string ID
            $rdv->patient_id = $user->id;
            $rdv->patient_name = $request->patient_name ?? $user->name;
            $rdv->patient_phone = $request->patient_phone ?? $user->phone;
            $rdv->patient_email = $request->patient_email ?? $user->email;
            $rdv->target_user_id = $doctor->id;
            $rdv->target_role = $request->target_role;
            // Save annonce_id to link appointment with announcement
            $rdv->annonce_id = $request->announcement_id ?? null;
            $rdv->date_time = $dateTime->format('Y-m-d H:i:s');
            $rdv->status = 'confirmed';
            $rdv->reason = $request->reason;
            $rdv->notes = $request->notes;
            
            \Log::info("Creating appointment with data: " . json_encode([
                'patient_id' => $user->id,
                'target_user_id' => $doctor->id,
                'target_role' => $request->target_role,
                'annonce_id' => $request->announcement_id ?? null,
                'date_time' => $dateTime->format('Y-m-d H:i:s'),
                'status' => 'confirmed',
                'reason' => $request->reason
            ]));
            
            $rdv->save();

            // Send notification to the doctor
            try {
                // Only send notification if the notification class exists and doctor has notification preferences
                if (class_exists('App\Notifications\AppointmentBooked')) {
                    $doctor->notify(new AppointmentBooked($rdv));
                    \Log::info("Notification sent to doctor {$doctor->id} for appointment {$rdv->id}");
                }
            } catch (\Exception $notificationError) {
                \Log::error("Failed to send notification: " . $notificationError->getMessage());
                // Continue even if notification fails
            }

            // Compute provider display name for response
            $roleLower = strtolower($request->target_role);
            $orgRoles = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie', 'organization'];
            $returnName = $doctor->name;
            if (in_array($roleLower, $orgRoles, true)) {
                $resolved = $this->getOrganizationDisplayName($doctor->id, $roleLower);
                if (!empty($resolved)) {
                    $returnName = $resolved;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous créé avec succès',
                'id' => $rdv->id,
                'appointment' => [
                    'id' => $rdv->id,
                    'doctor_id' => $doctor->id,
                    'doctor_name' => $returnName,
                    'patient_id' => $user->id,
                    'date' => $dateTime->format('Y-m-d'),
                    'time' => $dateTime->format('H:i'),
                    'status' => $rdv->status,
                    'reason' => $rdv->reason,
                ],
            ], 201);

        } catch (\Exception $e) {
            \Log::error("Failed to create doctor appointment: " . $e->getMessage());
            \Log::error("Stack trace: " . $e->getTraceAsString());
            
            return response()->json([
                'message' => 'Erreur lors de la création du rendez-vous',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Book a new appointment with organization
     */
    private function storeOrganizationAppointment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'organization_id' => 'required|exists:users,id',
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required',
            'reason' => 'nullable|string|max:500',
            'patientName' => 'required|string|max:255',
            'patientPhone' => 'required|string|max:20',
            'patientEmail' => 'nullable|email|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $organization = User::findOrFail($request->organization_id);

        // Check if organization is available (not in vacation mode)
        $organizationProfile = null;
        $orgRoleMap = [
            'clinique' => 'cliniqueProfile',
            'pharmacie' => 'pharmacieProfile',
            'parapharmacie' => 'parapharmacieProfile',
            'labo_analyse' => 'laboAnalyseProfile',
            'centre_radiologie' => 'centreRadiologieProfile'
        ];
        
        $orgRole = $organization->role->name ?? '';
        if (isset($orgRoleMap[$orgRole])) {
            $organization->load($orgRoleMap[$orgRole]);
            $organizationProfile = $organization->{$orgRoleMap[$orgRole]};
        }
        
        if ($organizationProfile && $organizationProfile->disponible === false) {
            return response()->json([
                'success' => false,
                'message' => 'Cet établissement est actuellement fermé et n\'accepte pas de nouveaux rendez-vous.'
            ], 400);
        }

        // Check if appointment slot is still available
        $dateTime = Carbon::parse($request->date . ' ' . $request->time);
        $existingAppointment = Rdv::where('target_user_id', $organization->id)
            ->where('date_time', $dateTime)
            ->where('status', '!=', 'cancelled')
            ->first();

        if ($existingAppointment) {
            return response()->json(['message' => 'Ce créneau n\'est plus disponible'], 409);
        }

        // Check if appointment is in the future
        $now = now();
        $minimumTime = $now->copy()->addMinutes(30);
        
        if ($dateTime <= $minimumTime) {
            return response()->json(['message' => 'Veuillez sélectionner une date et heure au moins 30 minutes dans le futur'], 400);
        }

        try {
            $rdv = new Rdv();
            $rdv->id = $this->generateAppointmentId();
            $rdv->patient_name = $request->patientName;
            $rdv->patient_phone = $request->patientPhone;
            $rdv->patient_email = $request->patientEmail;
            $rdv->target_user_id = $organization->id;
            $rdv->target_role = 'organization';
            $rdv->date_time = $dateTime;
            $rdv->status = 'scheduled';
            $rdv->reason = $request->reason ?? 'Consultation';
            $rdv->save();

            // Send notification to the organization
            try {
                $organization->notify(new AppointmentBooked($rdv));
                \Log::info("Notification sent to organization {$organization->id} for appointment {$rdv->id}");
            } catch (\Exception $notificationError) {
                \Log::error("Failed to send notification: " . $notificationError->getMessage());
                // Continue even if notification fails
            }

        } catch (\Exception $e) {
            \Log::error('Error creating appointment: ' . $e->getMessage());
            \Log::error('Full exception: ' . $e->getTraceAsString());
            \Log::error('Request data: ' . json_encode($request->all()));
            return response()->json([
                'message' => 'Erreur lors de la création du rendez-vous',
                'error' => $e->getMessage()
            ], 500);
        }

        // Resolve and return establishment name
        $orgName = $this->getOrganizationDisplayName($organization->id, $orgRole ?: 'organization') ?? $organization->name;

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous créé avec succès',
            'appointment' => [
                'id' => $rdv->id,
                'organization_id' => $organization->id,
                'organization_name' => $orgName,
                'patient_name' => $rdv->patient_name,
                'date' => $dateTime->format('Y-m-d'),
                'time' => $dateTime->format('H:i'),
                'status' => $rdv->status,
                'reason' => $rdv->reason,
            ],
        ], 201);
    }

    /**
     * Update an appointment
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();
        $userRole = is_object($user->role) ? $user->role->name : $user->role;
        
        if (strtolower($userRole) !== 'patient') {
            return response()->json(['message' => 'Seuls les patients peuvent modifier leurs rendez-vous'], 403);
        }

        $rdv = Rdv::where('id', $id)->where('patient_id', $user->id)->first();
        
        if (!$rdv) {
            return response()->json(['message' => 'Rendez-vous non trouvé'], 404);
        }

        $validator = Validator::make($request->all(), [
            'date' => 'required|date_format:Y-m-d',
            'time' => 'required',
            'reason' => 'sometimes|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Données invalides', 'errors' => $validator->errors()], 400);
        }

        // Check if new time slot is available
        $dateTime = Carbon::parse($request->date . ' ' . $request->time);
        
        // Special handling for midnight appointments
        if ($request->time === '00:00') {
            $dateTime = Carbon::parse($request->date . ' 00:00:00')->addDay();
        }

        // Check for conflicts (excluding current appointment)
        $existingAppointment = Rdv::where('target_user_id', $rdv->target_user_id)
            ->where('date_time', $dateTime)
            ->where('id', '!=', $id)
            ->first();

        if ($existingAppointment) {
            return response()->json(['message' => 'Ce créneau n\'est plus disponible'], 409);
        }

        // Check if appointment is in the future
        $now = now();
        $minimumTime = $now->copy()->addMinutes(30);
        
        if ($dateTime <= $minimumTime) {
            return response()->json(['message' => 'Veuillez sélectionner une date et heure au moins 30 minutes dans le futur'], 400);
        }

        try {
            // Store old date/time for notification
            $oldDateTime = $rdv->date_time;
            
            $rdv->date_time = $dateTime;
            if ($request->has('reason')) {
                $rdv->reason = $request->reason;
            }
            $rdv->save();

            $doctor = User::find($rdv->target_user_id);
            
            // Send notification to the doctor about the update
            $doctor->notify(new AppointmentUpdated($rdv, $oldDateTime));
            \Log::info("Update notification sent to doctor {$doctor->id} for appointment {$rdv->id}");

            // Compute provider display name for response (prefer establishment name for organizations)
            $roleLower = strtolower($rdv->target_role ?? '');
            $orgRoles = ['clinique', 'pharmacie', 'parapharmacie', 'labo_analyse', 'centre_radiologie', 'organization'];
            $returnName = $doctor ? $doctor->name : 'Prestataire';
            if ($doctor && in_array($roleLower, $orgRoles, true)) {
                $resolved = $this->getOrganizationDisplayName($doctor->id, $roleLower);
                if (!empty($resolved)) {
                    $returnName = $resolved;
                }
            }

            return response()->json([
                'message' => 'Rendez-vous modifié avec succès',
                'appointment' => [
                    'id' => $rdv->id,
                    'doctor_id' => $doctor->id,
                    'doctor_name' => $returnName,
                    'date' => $dateTime->format('Y-m-d'),
                    'time' => $dateTime->format('H:i'),
                    'status' => $rdv->status,
                    'reason' => $rdv->reason,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to update appointment: " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de la modification du rendez-vous'], 500);
        }
    }

    /**
     * Cancel/Delete an appointment
     */
    public function destroy(Request $request, $id)
    {
        $user = $request->user();
        $userRole = is_object($user->role) ? $user->role->name : $user->role;
        
        if (strtolower($userRole) !== 'patient') {
            return response()->json(['message' => 'Seuls les patients peuvent annuler leurs rendez-vous'], 403);
        }

        $rdv = Rdv::where('id', $id)->where('patient_id', $user->id)->first();
        
        if (!$rdv) {
            return response()->json(['message' => 'Rendez-vous non trouvé'], 404);
        }

        try {
            // Update status to canceled instead of deleting
            $rdv->status = 'canceled';
            $rdv->save();

            // Send notification to the doctor about the cancellation
            try {
                $doctor = User::find($rdv->target_user_id);
                if ($doctor) {
                    $doctor->notify(new AppointmentCancelled($rdv));
                    \Log::info("Cancellation notification sent to doctor {$doctor->id} for appointment {$rdv->id}");
                }
            } catch (\Exception $notificationError) {
                \Log::error("Failed to send cancellation notification: " . $notificationError->getMessage());
                // Continue with the response even if notification fails
            }

            return response()->json([
                'message' => 'Rendez-vous annulé avec succès',
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to cancel appointment: " . $e->getMessage());
            return response()->json(['message' => 'Erreur lors de l\'annulation du rendez-vous'], 500);
        }
    }

    /**
     * Cancel an appointment
     */
    public function cancel(Request $request, $id)
    {
        $rdv = Rdv::where('id', $id)
            ->where('patient_id', $request->user()->id)
            ->firstOrFail();

        $rdv->update(['status' => 'cancelled']);

        // Send notification to the doctor about the cancellation
        try {
            $doctor = User::find($rdv->target_user_id);
            if ($doctor) {
                $doctor->notify(new AppointmentCancelled($rdv));
                \Log::info("Cancellation notification sent to doctor {$doctor->id} for appointment {$rdv->id}");
            }
        } catch (\Exception $notificationError) {
            \Log::error("Failed to send cancellation notification: " . $notificationError->getMessage());
            // Continue with the response even if notification fails
        }

        return response()->json(['message' => 'Rendez-vous annulé', 'id' => $rdv->id]);
    }

    /**
     * Get booked time slots for a doctor on a specific date
     */
    public function getBookedSlots($doctorId, Request $request)
    {
        $date = $request->query('date');
        
        if (!$date) {
            return response()->json(['error' => 'Date parameter is required'], 400);
        }

        try {
            // Get all appointments for the doctor on the specified date
            $bookedAppointments = Rdv::where('target_user_id', $doctorId)
                ->whereDate('date_time', $date)
                ->where('status', '!=', 'canceled')
                ->get();

            // Extract time slots from appointments
            $bookedSlots = $bookedAppointments->map(function ($appointment) {
                return \Carbon\Carbon::parse($appointment->date_time)->format('H:i');
            })->toArray();

            return response()->json([
                'success' => true,
                'bookedSlots' => $bookedSlots
            ]);

        } catch (\Exception $e) {
            \Log::error('Error fetching booked slots: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Error fetching booked slots',
                'bookedSlots' => []
            ], 500);
        }
    }
}
