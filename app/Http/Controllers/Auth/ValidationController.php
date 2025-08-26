<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class ValidationController extends Controller
{
    /**
     * Check if email and phone number are available for registration
     */
    public function checkAvailability(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'phone' => 'required|string'
        ]);

        $email = $request->email;
        $phone = $request->phone;

        $errors = [];

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $errors['email'] = 'Cette adresse email est déjà utilisée.';
        }

        // Check if phone already exists
        if (User::where('phone', $phone)->exists()) {
            $errors['phone'] = 'Ce numéro de téléphone est déjà utilisé.';
        }

        if (!empty($errors)) {
            return response()->json([
                'available' => false,
                'errors' => $errors
            ], 422);
        }

        return response()->json([
            'available' => true,
            'message' => 'Email et téléphone disponibles'
        ], 200);
    }
}
