<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Rdv;

class AppointmentCancelled extends Notification
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
        $patient = \App\Models\User::find($this->rdv->patient_id);
        
        return [
            'title' => 'Rendez-vous annulé',
            'message' => "Le rendez-vous avec {$patient->name} du {$this->rdv->date_time->format('d/m/Y à H:i')} a été annulé",
            'type' => 'appointment_cancelled',
            'date' => now(),
            'appointment_id' => $this->rdv->id,
            'patient_id' => $this->rdv->patient_id,
            'patient_name' => $patient->name,
            'doctor_id' => $this->rdv->target_user_id,
            'appointment_date' => $this->rdv->date_time->format('Y-m-d'),
            'appointment_time' => $this->rdv->date_time->format('H:i'),
            'reason' => $this->rdv->reason,
            'status' => $this->rdv->status,
        ];
    }
}
