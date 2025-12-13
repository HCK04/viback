<?php

namespace App\Http\Controllers;

use App\Models\FamilyMember;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FamilyMemberController extends Controller
{
    /**
     * Display a listing of the user's family members.
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Check if user has a family subscription
        if (!$user->subscription || $user->subscription->plan_type !== 'family') {
            return response()->json([
                'message' => 'Cette fonctionnalité nécessite un abonnement familial.',
                'data' => []
            ], 200);
        }

        $familyMembers = FamilyMember::where('subscription_id', $user->subscription->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($familyMembers);
    }

    /**
     * Store a newly created family member.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        // Check if user can add family members
        if (!$user->canAddFamilyMembers()) {
            return response()->json([
                'message' => 'Vous devez avoir un abonnement familial actif pour ajouter des membres.'
            ], 403);
        }

        // Check if limit reached
        $currentCount = FamilyMember::where('subscription_id', $user->subscription->id)->count();
        $maxMembers = $user->getMaxFamilyMembers();

        if ($currentCount >= $maxMembers) {
            return response()->json([
                'message' => "Vous avez atteint la limite de {$maxMembers} membres de famille."
            ], 403);
        }

        // Validate input
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'relationship' => 'required|string|max:100',
            'age' => 'nullable|integer|min:0|max:150',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'blood_type' => 'nullable|string|max:10',
        ]);

        // Check if an existing user has this email
        $existingUser = null;
        if (!empty($validated['email'])) {
            $existingUser = \App\Models\User::where('email', $validated['email'])->first();
        }

        // Create family member
        $familyMember = FamilyMember::create([
            'subscription_id' => $user->subscription->id,
            'patient_id' => $existingUser ? $existingUser->id : null,
            'name' => $validated['name'],
            'relationship' => $validated['relationship'],
            'age' => $validated['age'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'blood_type' => $validated['blood_type'] ?? null,
            'discount_percentage' => 10.00, // Default 10% discount
        ]);

        return response()->json($familyMember, 201);
    }

    /**
     * Display the specified family member.
     */
    public function show($id)
    {
        $user = Auth::user();

        $familyMember = FamilyMember::where('id', $id)
            ->whereHas('subscription', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$familyMember) {
            return response()->json([
                'message' => 'Membre de famille non trouvé.'
            ], 404);
        }

        return response()->json($familyMember);
    }

    /**
     * Update the specified family member.
     */
    public function update(Request $request, $id)
    {
        $user = Auth::user();

        $familyMember = FamilyMember::where('id', $id)
            ->whereHas('subscription', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$familyMember) {
            return response()->json([
                'message' => 'Membre de famille non trouvé.'
            ], 404);
        }

        // Validate input
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'relationship' => 'sometimes|required|string|max:100',
            'age' => 'nullable|integer|min:0|max:150',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'blood_type' => 'nullable|string|max:10',
        ]);

        $familyMember->update($validated);

        return response()->json($familyMember);
    }

    /**
     * Remove the specified family member.
     */
    public function destroy($id)
    {
        $user = Auth::user();

        $familyMember = FamilyMember::where('id', $id)
            ->whereHas('subscription', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->first();

        if (!$familyMember) {
            return response()->json([
                'message' => 'Membre de famille non trouvé.'
            ], 404);
        }

        $familyMember->delete();

        return response()->json([
            'message' => 'Membre de famille supprimé avec succès.'
        ]);
    }

    /**
     * Get the count of family members for the current user.
     */
    public function count()
    {
        $user = Auth::user();

        if (!$user->subscription) {
            return response()->json([
                'count' => 0,
                'max' => 0,
                'can_add' => false
            ]);
        }

        $count = FamilyMember::where('subscription_id', $user->subscription->id)->count();
        $max = $user->getMaxFamilyMembers();

        return response()->json([
            'count' => $count,
            'max' => $max,
            'can_add' => $user->canAddFamilyMembers() && $count < $max
        ]);
    }
}
