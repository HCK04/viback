<?php
// Create a new controller for efficient dashboard data



namespace App\Http\Controllers;

use App\Models\Rdv;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get patient dashboard statistics
     */
    public function getPatientStats(Request $request)
    {
        $user = auth()->user();
        
        // Get count of upcoming appointments
        $upcomingAppointments = Rdv::where('patient_id', $user->id)
            ->where('date_rdv', '>=', now()->format('Y-m-d'))
            ->count();
            
        // Get count of completed appointments
        $completedAppointments = Rdv::where('patient_id', $user->id)
            ->where('date_rdv', '<', now()->format('Y-m-d'))
            ->count();
            
        // Get count of notifications
        $notifications = 0; // Replace with actual notification count when implemented
            
        return response()->json([
            'upcomingAppointments' => $upcomingAppointments,
            'completedAppointments' => $completedAppointments,
            'notifications' => $notifications
        ]);
    }

    /**
     * Get statistics for doctor dashboard
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function doctorStats(Request $request)
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $today = now()->format('Y-m-d');
            
            // Get upcoming appointments (from today onwards)
            $appointmentsToday = Rdv::where('target_user_id', $userId)
                ->where('date_time', '>=', now())
                ->count();
            
            // Count unique patients
            $totalPatients = Rdv::where('target_user_id', $userId)
                ->distinct('patient_id')
                ->count('patient_id');
            
            // Count total appointments
            $totalAppointments = Rdv::where('target_user_id', $userId)
                ->count();
            
            // Calculate revenue (assuming each appointment has a price field)
            $revenue = Rdv::where('target_user_id', $userId)
                ->where('status', 'completed')
                ->sum('price') ?? 0;
            
            return response()->json([
                'appointmentsUpcoming' => $appointmentsToday,
                'totalPatients' => $totalPatients,
                'totalAppointments' => $totalAppointments,
                'revenue' => $revenue,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in doctorStats: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get doctor appointments
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function doctorAppointments(Request $request)
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $appointments = Rdv::with(['patient'])
                ->where('target_user_id', $userId)
                ->orderBy('date_time', 'desc')
                ->get()
                ->map(function ($appointment) {
                    return [
                        'id' => $appointment->id,
                        'date' => $appointment->date_time ? date('Y-m-d', strtotime($appointment->date_time)) : null,
                        'time_start' => $appointment->date_time ? date('H:i', strtotime($appointment->date_time)) : null,
                        'time_end' => null, // Not available in current schema
                        'status' => $appointment->status ?? 'pending',
                        'patient_name' => $appointment->patient->name ?? 'Patient',
                        'patient_id' => $appointment->patient_id,
                        'notes' => $appointment->notes ?? '',
                        'price' => 0, // Not available in current schema
                        'reason' => $appointment->reason ?? '',
                    ];
                });
            
            return response()->json($appointments);
        } catch (\Exception $e) {
            \Log::error('Error in doctorAppointments: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get appointment details by ID
     */
    public function getAppointmentDetails($id)
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $appointment = Rdv::with(['patient', 'annonce'])
                ->where('id', $id)
                ->where('target_user_id', $userId)
                ->first();
            
            // Debug logging
            \Log::info('Appointment data:', [
                'appointment_id' => $appointment ? $appointment->id : 'null',
                'annonce_id' => $appointment ? $appointment->annonce_id : 'null',
                'annonce_data' => $appointment && $appointment->annonce ? $appointment->annonce->toArray() : 'null',
                'raw_appointment' => $appointment ? $appointment->toArray() : 'null'
            ]);
            
            if (!$appointment) {
                return response()->json([
                    'error' => 'Appointment not found'
                ], 404);
            }
            
            // Get price from annonce - no default
            $price = $appointment->annonce ? $appointment->annonce->price : null;

            return response()->json([
                'id' => $appointment->id,
                'date' => $appointment->date_time ? \Carbon\Carbon::parse($appointment->date_time)->format('Y-m-d') : null,
                'time' => $appointment->date_time ? \Carbon\Carbon::parse($appointment->date_time)->format('H:i') : null,
                'date_time' => $appointment->date_time,
                'status' => $appointment->status ?? 'pending',
                'reason' => $appointment->reason ?? 'Consultation',
                'notes' => $appointment->notes ?? '',
                'price' => $price,
                'annonce_id' => $appointment->annonce_id, // Add annonce_id to response
                'patient_phone' => $appointment->patient_phone ?? ($appointment->patient->phone ?? ''),
                'patient_email' => $appointment->patient_email ?? ($appointment->patient->email ?? ''),
                'patient' => [
                    'id' => $appointment->patient->id ?? null,
                    'name' => $appointment->patient->name ?? 'Patient',
                    'email' => $appointment->patient->email ?? '',
                    'phone' => $appointment->patient->phone ?? '',
                ],
                'annonce' => $appointment->annonce ? [
                    'id' => $appointment->annonce->id,
                    'title' => $appointment->annonce->title,
                    'description' => $appointment->annonce->description,
                    'price' => $appointment->annonce->price ?? 0,
                    'pourcentage_reduction' => $appointment->annonce->pourcentage_reduction ?? 0,
                    'address' => $appointment->annonce->address ?? '',
                ] : null,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in getAppointmentDetails: ' . $e->getMessage());
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update appointment status
     */
    public function updateAppointmentStatus(Request $request, $id)
    {
        try {
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json([
                    'error' => 'User not authenticated'
                ], 401);
            }
            
            $request->validate([
                'status' => 'required|in:confirmed,cancelled,pending,completed,missed'
            ]);
            
            $appointment = Rdv::where('id', $id)
                ->where('target_user_id', $userId)
                ->first();
            
            if (!$appointment) {
                return response()->json([
                    'error' => 'Appointment not found'
                ], 404);
            }
            
            $appointment->status = $request->status;
            $appointment->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Appointment status updated successfully',
                'appointment' => [
                    'id' => $appointment->id,
                    'status' => $appointment->status,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in updateAppointmentStatus: ' . $e->getMessage());
            return response()->json([
                'error' => 'Internal server error: ' . $e->getMessage()
            ], 500);
        }
    }
}
