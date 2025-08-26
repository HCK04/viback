<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use App\Models\Rdv;

class AppointmentUpdated extends Notification
{
    use Queueable;

    protected $rdv;
    protected $oldDateTime;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(Rdv $rdv, $oldDateTime = null)
    {
        $this->rdv = $rdv;
        $this->oldDateTime = $oldDateTime;
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
            'title' => 'Rendez-vous modifié',
            'message' => "Le rendez-vous avec {$patient->name} a été modifié pour le {$this->rdv->date_time->format('d/m/Y à H:i')}",
            'type' => 'appointment_updated',
            'date' => now(),
            'appointment_id' => $this->rdv->id,
            'patient_id' => $this->rdv->patient_id,
            'patient_name' => $patient->name,
            'doctor_id' => $this->rdv->target_user_id,
            'appointment_date' => $this->rdv->date_time->format('Y-m-d'),
            'appointment_time' => $this->rdv->date_time->format('H:i'),
            'old_date_time' => $this->oldDateTime ? $this->oldDateTime->format('d/m/Y à H:i') : null,
            'reason' => $this->rdv->reason,
            'status' => $this->rdv->status,
        ];
    }
}
