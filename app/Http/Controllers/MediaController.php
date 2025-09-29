<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Stream the authenticated professional's own carte_professionnelle file.
     * Only the owner can access; no userId parameter to prevent IDOR.
     */
    public function showProfessionalCarte(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Resolve current professional profile by role name or by available relations
        $profile = null;
        try { $roleEntity = $user->role; } catch (\Throwable $e) { $roleEntity = null; }
        $roleName = strtolower(($roleEntity->name ?? ($user->role_name ?? '')));

        if (in_array($roleName, ['medecin','doctor'])) {
            $profile = $user->medecinProfile;
        } elseif ($roleName === 'kine') {
            $profile = $user->kineProfile;
        } elseif ($roleName === 'orthophoniste') {
            $profile = $user->orthophonisteProfile;
        } elseif ($roleName === 'psychologue') {
            $profile = $user->psychologueProfile;
        }

        if (!$profile) {
            // Fallback: try to detect any individual professional profile
            $profile = ($user->medecinProfile
                ?: ($user->kineProfile
                ?: ($user->orthophonisteProfile
                ?: ($user->psychologueProfile ?: null))));
        }

        if (!$profile || empty($profile->carte_professionnelle)) {
            return response()->json(['message' => 'No professional card on file'], 404);
        }

        // Normalize path coming from DB to a relative path on public disk
        $raw = (string)$profile->carte_professionnelle;
        $raw = str_replace('\\', '/', $raw);
        // Accept forms like `/storage/cartes_professionnelles/foo.pdf` or `cartes_professionnelles/foo.pdf`
        $relative = ltrim(str_replace(['/storage/', 'public/'], ['', ''], $raw), '/');

        // Enforce folder restriction
        if (stripos($relative, 'cartes_professionnelles/') !== 0) {
            return response()->json(['message' => 'Invalid file path'], 400);
        }

        if (!Storage::disk('public')->exists($relative)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        // Guess mime and stream
        $mime = Storage::disk('public')->mimeType($relative) ?: 'application/octet-stream';
        $stream = Storage::disk('public')->readStream($relative);
        if (!$stream) {
            return response()->json(['message' => 'Unable to read file'], 500);
        }

        return new StreamedResponse(function () use ($stream) {
            while (!feof($stream)) {
                echo fread($stream, 8192);
            }
            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, [
            'Content-Type' => $mime,
            // Prevent indexing and caching
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
