<?php

namespace App\Listeners;

use App\Services\ExpoPushService;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Log;

class SendPushNotificationListener
{
    protected $expoPushService;

    /**
     * Create the event listener.
     *
     * @param  \App\Services\ExpoPushService  $expoPushService
     * @return void
     */
    public function __construct(ExpoPushService $expoPushService)
    {
        $this->expoPushService = $expoPushService;
    }

    /**
     * Handle the event.
     *
     * @param  \Illuminate\Notifications\Events\NotificationSent  $event
     * @return void
     */
    public function handle(NotificationSent $event)
    {
        // Only send push for database notifications
        if ($event->channel !== 'database') {
            return;
        }

        $notifiable = $event->notifiable;
        $notification = $event->notification;

        // Ensure notifiable is a User model with an ID
        if (!$notifiable || !method_exists($notifiable, 'getKey')) {
            return;
        }

        try {
            // Get notification data
            $data = method_exists($notification, 'toArray') 
                ? $notification->toArray($notifiable) 
                : [];

            $title = $data['title'] ?? 'Nouvelle notification';
            $body = $data['message'] ?? '';

            // Prepare deep link data
            $pushData = [
                'type' => $data['type'] ?? 'notification',
                'appointment_id' => $data['appointment_id'] ?? null,
                'patient_id' => $data['patient_id'] ?? null,
                'doctor_id' => $data['doctor_id'] ?? null,
            ];

            // Remove null values
            $pushData = array_filter($pushData, function($value) {
                return $value !== null;
            });

            // Send push notification asynchronously
            $this->expoPushService->sendToUser(
                $notifiable->getKey(),
                $title,
                $body,
                $pushData
            );

        } catch (\Exception $e) {
            // Log error but don't throw - push failures shouldn't break the notification flow
            Log::error('Failed to send push notification', [
                'user_id' => $notifiable->getKey(),
                'notification_type' => get_class($notification),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
}
