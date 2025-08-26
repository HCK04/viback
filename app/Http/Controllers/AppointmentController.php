<?php

namespace App\Http\Controllers;

use App\Models\Rdv;
use App\Models\User;
use App\Models\MedecinProfile;
use App\Models\Annonce;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
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
                // Handle organization appointments vs regular appointments
                $providerName = $a->target?->name ?? 'Établissement';
                
                // For organization appointments, use stored patient info
                if ($a->target_role === 'organization') {
                    $providerName = $a->target?->name ?? 'Organisation';
                }
                
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
                    'is_organization_appointment' => $a->target_role === 'organization',
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

            // Format the appointment data
            $appointment = [
                'id' => $rdv->id,
                'doctor_name' => $rdv->target ? $rdv->target->name : 'Médecin inconnu',
                'date' => $rdv->date_time ? Carbon::parse($rdv->date_time)->format('Y-m-d') : null,
                'time' => $rdv->date_time ? Carbon::parse($rdv->date_time)->format('H:i') : null,
                'reason' => $rdv->reason ?? 'Consultation',
                'status' => $rdv->status ?? 'scheduled',
                'patient_name' => $user->name,
                'patient_phone' => $rdv->patient_phone ?? $user->phone ?? null,
                'patient_email' => $rdv->patient_email ?? $user->email ?? null,
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
            'target_role' => 'required|string|in:doctor,medecin',
            'date_time' => 'required|string',
            'reason' => 'required|string|max:500',
            'patient_name' => 'nullable|string|max:255',
            'patient_phone' => 'nullable|string|max:20',
            'patient_email' => 'nullable|email|max:255',
            'announcement_id' => 'nullable|exists:annonces,id',
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

        // Parse date_time with explicit format
        try {
            // Expected format: "2025-08-24 20:58"
            $dateTime = Carbon::createFromFormat('Y-m-d H:i', $request->date_time);
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
            $rdv->target_role = 'medecin';
            // Save annonce_id to link appointment with announcement
            $rdv->annonce_id = $request->announcement_id ?? null;
            $rdv->date_time = $dateTime->format('Y-m-d H:i:s');
            $rdv->status = 'scheduled';
            $rdv->reason = $request->reason;
            
            \Log::info("Creating appointment with data: " . json_encode([
                'patient_id' => $user->id,
                'target_user_id' => $doctor->id,
                'target_role' => 'medecin',
                'annonce_id' => $request->announcement_id ?? null,
                'date_time' => $dateTime->format('Y-m-d H:i:s'),
                'status' => 'scheduled',
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

            return response()->json([
                'success' => true,
                'message' => 'Rendez-vous créé avec succès',
                'appointment' => [
                    'id' => $rdv->id,
                    'doctor_id' => $doctor->id,
                    'doctor_name' => $doctor->name,
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

        return response()->json([
            'success' => true,
            'message' => 'Rendez-vous créé avec succès',
            'appointment' => [
                'id' => $rdv->id,
                'organization_id' => $organization->id,
                'organization_name' => $organization->name,
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

            return response()->json([
                'message' => 'Rendez-vous modifié avec succès',
                'appointment' => [
                    'id' => $rdv->id,
                    'doctor_id' => $doctor->id,
                    'doctor_name' => $doctor->name,
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
