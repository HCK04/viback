<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;

class LoginController extends Controller
{
    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        // Update validation to only require email and password
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            // Remove username validation - it doesn't exist in your schema
        ]);

        // Log what we're attempting to authenticate with
        Log::info('Login attempt', [
            'email' => $request->email,
            // Don't log the password for security reasons
        ]);

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            
            // Revoke existing tokens
            $user->tokens()->delete();
            
            // Create a new token
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Get role name
            $roleName = $user->role ? $user->role->name : null;
            
            Log::info('Login successful', [
                'user_id' => $user->id,
                'role' => $roleName,
            ]);
            
            return response()->json([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role_id' => $user->role_id,
                    'role_name' => $roleName,
                ],
                'token' => $token,
            ]);
        }

        Log::warning('Login failed', [
            'email' => $request->email,
        ]);

        throw ValidationException::withMessages([
            'email' => ['Les identifiants fournis ne correspondent pas à nos données.'],
        ]);
    }

    /**
     * Log the user out (Invalidate the token).
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revoke all tokens
        $request->user()->tokens()->delete();
        
        return response()->json(['message' => 'Successfully logged out']);
    }
}
