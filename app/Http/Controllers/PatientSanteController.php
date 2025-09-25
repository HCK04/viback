<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\PatientProfile;

class PatientSanteController extends Controller
{
    private const VALID_SECTIONS = [
        'documents',
        'antecedents_medicaux',
        'traitements_reguliers',
        'allergies',
        'antecedents_familiaux',
        'operations_chirurgicales',
        'vaccins',
        'mesures',
    ];

    private function ensureProfile($user): PatientProfile
    {
        $patientProfile = $user->patientProfile;
        if (!$patientProfile) {
            $patientProfile = new PatientProfile();
            $patientProfile->user_id = $user->id;
            $patientProfile->save();
        }
        return $patientProfile;
    }

    private function col(string $section): string
    {
        return 'sante_' . $section;
    }

    private function noneCol(string $section): string
    {
        return 'sante_' . $section . '_none';
    }

    private function assertPatient($user)
    {
        // role_id 1 assumed to be patient based on existing code
        if (!$user || (int)$user->role_id !== 1) {
            abort(403, 'Only patients can manage Santé data');
        }
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $this->assertPatient($user);
        $profile = $this->ensureProfile($user);

        $data = [];
        foreach (self::VALID_SECTIONS as $section) {
            $data[$section] = [
                'items' => $profile->{$this->col($section)} ?? [],
                'none' => (bool)($profile->{$this->noneCol($section)} ?? false)
            ];
        }

        return response()->json(['sante' => $data]);
    }

    public function updateSection(Request $request, string $section)
    {
        $user = $request->user();
        $this->assertPatient($user);
        if (!in_array($section, self::VALID_SECTIONS, true)) {
            return response()->json(['error' => 'Invalid section'], 422);
        }

        // Vaccins has dedicated endpoints
        if ($section === 'vaccins') {
            return response()->json(['error' => 'Use dedicated endpoint for this section'], 422);
        }

        $validated = $request->validate([
            'items' => 'nullable|array',
            'none' => 'nullable|boolean',
        ]);

        $profile = $this->ensureProfile($user);

        $col = $this->col($section);
        $noneCol = $this->noneCol($section);

        if (array_key_exists('none', $validated)) {
            $profile->{$noneCol} = (bool)$validated['none'];
            if ($validated['none'] === true) {
                // clear items if user declares none
                $profile->{$col} = [];
            }
        }

        // For documents, we only allow toggling the NONE flag in this endpoint; items must be
        // managed through upload/delete dedicated endpoints
        if ($section === 'documents' && array_key_exists('items', $validated)) {
            return response()->json(['error' => 'Use upload/delete endpoints to manage documents list'], 422);
        }

        if (array_key_exists('items', $validated)) {
            $profile->{$col} = $validated['items'] ?? [];
            // if items provided, unset none
            if (is_array($validated['items']) && count($validated['items']) > 0) {
                $profile->{$noneCol} = false;
            }
        }

        $profile->save();

        return response()->json([
            'message' => 'Section updated',
            'section' => $section,
            'data' => [
                'items' => $profile->{$col} ?? [],
                'none' => (bool)($profile->{$noneCol} ?? false)
            ]
        ]);
    }

    public function addVaccine(Request $request)
    {
        $user = $request->user();
        $this->assertPatient($user);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
        ]);

        $profile = $this->ensureProfile($user);
        $list = is_array($profile->sante_vaccins) ? $profile->sante_vaccins : [];

        $entry = [
            'id' => str_replace('.', '_', uniqid('v_', true)),
            'name' => $validated['name'],
            'date' => $validated['date'],
        ];
        $list[] = $entry;

        $profile->sante_vaccins = $list;
        $profile->sante_vaccins_none = false; // since we added one
        $profile->save();

        return response()->json(['message' => 'Vaccine added', 'vaccine' => $entry, 'vaccins' => $profile->sante_vaccins]);
    }

    public function deleteVaccine(Request $request, string $id)
    {
        $user = $request->user();
        $this->assertPatient($user);
        $profile = $this->ensureProfile($user);
        $list = is_array($profile->sante_vaccins) ? $profile->sante_vaccins : [];
        $before = count($list);
        $list = array_values(array_filter($list, function ($v) use ($id) {
            return ($v['id'] ?? null) !== $id;
        }));
        $profile->sante_vaccins = $list;
        $profile->save();

        if (count($list) === $before) {
            return response()->json(['error' => 'Not found'], 404);
        }
        return response()->json(['message' => 'Deleted', 'vaccins' => $list]);
    }

    public function toggleVaccinesNone(Request $request)
    {
        $user = $request->user();
        $this->assertPatient($user);
        $validated = $request->validate([
            'none' => 'required|boolean',
        ]);
        $profile = $this->ensureProfile($user);
        $profile->sante_vaccins_none = (bool)$validated['none'];
        if ($validated['none']) {
            $profile->sante_vaccins = [];
        }
        $profile->save();
        return response()->json(['message' => 'Updated', 'none' => $profile->sante_vaccins_none]);
    }

    public function catalogVaccines()
    {
        $catalog = [
            'BCG', 'Hépatite B', 'Polio', 'DTaP (Diphtérie, Tétanos, Coqueluche)', 'Hib', 'Pneumocoque', 'Rougeole', 'Rubéole', 'Oreillons', 'Varicelle', 'HPV', 'Grippe', 'COVID-19', 'Fièvre jaune', 'Hépatite A', 'Méningocoque', 'Typhoïde'
        ];
        return response()->json(['catalog' => $catalog]);
    }

    public function uploadDocument(Request $request)
    {
        $user = $request->user();
        $this->assertPatient($user);
        $request->validate([
            'file' => 'required|file|max:5120', // 5MB
        ]);

        $profile = $this->ensureProfile($user);

        $file = $request->file('file');
        $filename = time() . '_' . preg_replace('/[^A-Za-z0-9_\-.]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('patients/' . $user->id . '/documents', $filename, 'public');

        $docs = is_array($profile->sante_documents) ? $profile->sante_documents : [];
        $entry = [
            'id' => str_replace('.', '_', uniqid('d_', true)),
            'name' => $file->getClientOriginalName(),
            'path' => '/storage/' . $path,
            'uploaded_at' => now()->toDateTimeString(),
        ];
        $docs[] = $entry;
        $profile->sante_documents = $docs;
        $profile->sante_documents_none = false;
        $profile->save();

        return response()->json(['message' => 'Uploaded', 'document' => $entry, 'documents' => $docs]);
    }

    public function deleteDocument(Request $request, string $id)
    {
        $user = $request->user();
        $this->assertPatient($user);
        $profile = $this->ensureProfile($user);
        $docs = is_array($profile->sante_documents) ? $profile->sante_documents : [];
        $deleted = null;
        $remaining = [];
        foreach ($docs as $doc) {
            if (($doc['id'] ?? null) === $id) {
                $deleted = $doc;
                // Delete the file if exists
                $relative = str_replace('/storage/', '', $doc['path'] ?? '');
                if ($relative && Storage::disk('public')->exists($relative)) {
                    Storage::disk('public')->delete($relative);
                }
            } else {
                $remaining[] = $doc;
            }
        }
        if (!$deleted) {
            return response()->json(['error' => 'Not found'], 404);
        }
        $profile->sante_documents = $remaining;
        $profile->save();
        return response()->json(['message' => 'Deleted', 'documents' => $remaining]);
    }
}
