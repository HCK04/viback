<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'medecin_profiles',
            'kine_profiles',
            'orthophoniste_profiles',
            'psychologue_profiles',
            'clinique_profiles',
            'pharmacie_profiles',
            'parapharmacie_profiles',
            'labo_analyse_profiles',
            'centre_radiologie_profiles',
        ];

        foreach ($tables as $tbl) {
            if (!Schema::hasColumn($tbl, 'imgs')) {
                Schema::table($tbl, function (Blueprint $table) use ($tbl) {
                    $table->json('imgs')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'medecin_profiles',
            'kine_profiles',
            'orthophoniste_profiles',
            'psychologue_profiles',
            'clinique_profiles',
            'pharmacie_profiles',
            'parapharmacie_profiles',
            'labo_analyse_profiles',
            'centre_radiologie_profiles',
        ];

        foreach ($tables as $tbl) {
            if (Schema::hasColumn($tbl, 'imgs')) {
                Schema::table($tbl, function (Blueprint $table) {
                    $table->dropColumn('imgs');
                });
            }
        }
    }
};
