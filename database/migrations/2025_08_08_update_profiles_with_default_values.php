<?php
 

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Profile;
use App\Models\PatientProfile;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing profiles with default values
        $profiles = Profile::whereNull('allergies')
            ->orWhereNull('chronic_diseases')
            ->orWhereNull('gender')
            ->orWhereNull('blood_type')
            ->get();

        foreach ($profiles as $profile) {
            $profile->allergies = $profile->allergies ?? "Aucune";
            $profile->chronic_diseases = $profile->chronic_diseases ?? "Aucune";
            $profile->gender = $profile->gender ?? "";
            $profile->blood_type = $profile->blood_type ?? "";
            $profile->save();
        }

        // Also update patient profiles
        $patientProfiles = PatientProfile::whereNull('allergies')
            ->orWhereNull('chronic_diseases')
            ->orWhereNull('gender')
            ->orWhereNull('blood_type')
            ->get();

        foreach ($patientProfiles as $profile) {
            $profile->allergies = $profile->allergies ?? "Aucune";
            $profile->chronic_diseases = $profile->chronic_diseases ?? "Aucune";
            $profile->gender = $profile->gender ?? "";
            $profile->blood_type = $profile->blood_type ?? "";
            $profile->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed
    }
};
