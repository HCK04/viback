<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoPushService
{
    const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';
    const EXPO_RECEIPT_URL = 'https://exp.host/--/api/v2/push/getReceipts';
    const CHUNK_SIZE = 100; // Expo limit

    /**
     * Send push notifications to a user's devices.
     *
     * @param int $userId
     * @param string $title
     * @param string $body
     * @param array $data Additional data for deep linking
     * @return array
     */
    public function sendToUser($userId, $title, $body, $data = [])
    {
        $tokens = DeviceToken::where('user_id', $userId)->pluck('expo_push_token')->toArray();

        if (empty($tokens)) {
            Log::info("No device tokens found for user {$userId}");
            return ['success' => true, 'sent' => 0];
        }

        return $this->sendPushNotifications($tokens, $title, $body, $data);
    }

    /**
     * Send push notifications to specific tokens.
     *
     * @param array $tokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @return array
     */
    public function sendPushNotifications($tokens, $title, $body, $data = [])
    {
        $messages = [];
        
        foreach ($tokens as $token) {
            $messages[] = [
                'to' => $token,
                'sound' => 'default',
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'priority' => 'high',
                'channelId' => 'default',
            ];
        }

        // Chunk messages to respect Expo's limit
        $chunks = array_chunk($messages, self::CHUNK_SIZE);
        $ticketIds = [];
        $errors = [];

        foreach ($chunks as $chunk) {
            try {
                $response = Http::withHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->timeout(30)
                ->post(self::EXPO_PUSH_URL, $chunk);

                if ($response->successful()) {
                    $responseData = $response->json();
                    
                    if (isset($responseData['data'])) {
                        foreach ($responseData['data'] as $index => $ticket) {
                            if (isset($ticket['status']) && $ticket['status'] === 'error') {
                                $this->handlePushError($chunk[$index]['to'], $ticket);
                                $errors[] = $ticket;
                            } elseif (isset($ticket['id'])) {
                                $ticketIds[] = $ticket['id'];
                            }
                        }
                    }
                } else {
                    Log::error('Expo Push API error', [
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    $errors[] = ['error' => 'HTTP ' . $response->status()];
                }
            } catch (\Exception $e) {
                Log::error('Exception sending push notification', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errors[] = ['error' => $e->getMessage()];
            }
        }

        return [
            'success' => true,
            'sent' => count($ticketIds),
            'errors' => $errors,
            'ticket_ids' => $ticketIds,
        ];
    }

    /**
     * Handle push notification errors (e.g., invalid tokens).
     *
     * @param string $token
     * @param array $ticket
     * @return void
     */
    protected function handlePushError($token, $ticket)
    {
        $details = $ticket['details'] ?? [];
        $error = $details['error'] ?? $ticket['message'] ?? 'Unknown error';

        Log::warning('Push notification error', [
            'token' => $token,
            'error' => $error,
            'ticket' => $ticket
        ]);

        // Remove invalid tokens
        if (in_array($error, ['DeviceNotRegistered', 'InvalidCredentials', 'MessageTooBig'])) {
            DeviceToken::where('expo_push_token', $token)->delete();
            Log::info("Removed invalid device token: {$token}");
        }
    }

    /**
     * Retrieve push notification receipts.
     *
     * @param array $ticketIds
     * @return array
     */
    public function getReceipts($ticketIds)
    {
        if (empty($ticketIds)) {
            return [];
        }

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->post(self::EXPO_RECEIPT_URL, ['ids' => $ticketIds]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? [];
            }

            Log::error('Failed to fetch receipts', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);
        } catch (\Exception $e) {
            Log::error('Exception fetching receipts', [
                'message' => $e->getMessage()
            ]);
        }

        return [];
    }
}
