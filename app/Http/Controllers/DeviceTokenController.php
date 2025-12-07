<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    /**
     * Register or update a device token for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string|max:255',
            'platform' => 'nullable|in:ios,android',
            'app_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $token = $request->input('token');

        // Validate Expo push token format
        if (!DeviceToken::isValidExpoPushToken($token)) {
            return response()->json([
                'message' => 'Invalid Expo push token format'
            ], 422);
        }

        $user = Auth::user();

        // Upsert: update if exists, create if not
        $deviceToken = DeviceToken::updateOrCreate(
            [
                'user_id' => $user->id,
                'expo_push_token' => $token,
            ],
            [
                'platform' => $request->input('platform'),
                'app_version' => $request->input('app_version'),
                'last_used_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Device token registered successfully',
            'token_id' => $deviceToken->id,
        ], 200);
    }

    /**
     * Unregister a device token.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $token
     * @return \Illuminate\Http\JsonResponse
     */
    public function unregister(Request $request, $token)
    {
        $user = Auth::user();

        // Only allow users to delete their own tokens
        $deleted = DeviceToken::where('user_id', $user->id)
            ->where('expo_push_token', $token)
            ->delete();

        if ($deleted) {
            return response()->json([
                'message' => 'Device token unregistered successfully'
            ], 200);
        }

        return response()->json([
            'message' => 'Device token not found'
        ], 404);
    }

    /**
     * Get all device tokens for the authenticated user (for debugging).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $tokens = DeviceToken::where('user_id', $user->id)
            ->select('id', 'platform', 'app_version', 'last_used_at', 'created_at')
            ->orderBy('last_used_at', 'desc')
            ->get();

        return response()->json([
            'tokens' => $tokens
        ], 200);
    }
}
