<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Rdv;

class AppointmentBooked extends Notification
{
    use Queueable;

    protected $rdv;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Rdv $rdv)
    {
        $this->rdv = $rdv;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        // For organization appointments, patient info is stored directly in rdv table
        $patientName = $this->rdv->patient_name ?? 'Patient';
        $patientId = $this->rdv->patient_id;
        
        // If patient_id exists, try to get user info, otherwise use stored patient info
        if ($patientId) {
            $patient = \App\Models\User::find($patientId);
            $patientName = $patient ? $patient->name : $this->rdv->patient_name;
        }
        
        return [
            'title' => 'Nouveau rendez-vous',
            'message' => "Un nouveau rendez-vous a été pris par {$patientName} pour le {$this->rdv->date_time->format('d/m/Y à H:i')}",
            'type' => 'appointment_booked',
            'date' => now(),
            'appointment_id' => $this->rdv->id,
            'patient_id' => $patientId,
            'patient_name' => $patientName,
            'doctor_id' => $this->rdv->target_user_id,
            'appointment_date' => $this->rdv->date_time->format('Y-m-d'),
            'appointment_time' => $this->rdv->date_time->format('H:i'),
            'reason' => $this->rdv->reason,
            'status' => $this->rdv->status,
        ];
    }
}
