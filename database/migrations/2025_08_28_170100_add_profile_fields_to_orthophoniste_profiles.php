<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orthophoniste_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('orthophoniste_profiles', 'horaire_start')) {
                $table->string('horaire_start')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'horaire_end')) {
                $table->string('horaire_end')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'ville')) {
                $table->string('ville')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'additional_info')) {
                $table->text('additional_info')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'numero_carte_professionnelle')) {
                $table->string('numero_carte_professionnelle')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'moyens_paiement')) {
                $table->json('moyens_paiement')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'moyens_transport')) {
                $table->json('moyens_transport')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'informations_pratiques')) {
                $table->text('informations_pratiques')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'jours_disponibles')) {
                $table->json('jours_disponibles')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'contact_urgence')) {
                $table->string('contact_urgence')->nullable();
            }
            if (!Schema::hasColumn('orthophoniste_profiles', 'rdv_patients_suivis_uniquement')) {
                $table->boolean('rdv_patients_suivis_uniquement')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orthophoniste_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'horaire_start',
                'horaire_end', 
                'ville',
                'additional_info',
                'numero_carte_professionnelle',
                'moyens_paiement',
                'moyens_transport',
                'informations_pratiques',
                'jours_disponibles',
                'contact_urgence',
                'rdv_patients_suivis_uniquement'
            ]);
        });
    }
};
