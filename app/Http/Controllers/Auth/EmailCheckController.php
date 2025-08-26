<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class EmailCheckController extends Controller
{
    public function check(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $exists = User::where('email', $request->email)->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Cet email est déjà utilisé'
            ], 422);
        }

        return response()->json([
            'message' => 'Email disponible'
        ], 200);
    }
}
