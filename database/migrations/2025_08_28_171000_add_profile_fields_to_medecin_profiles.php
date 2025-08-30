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
        Schema::table('medecin_profiles', function (Blueprint $table) {
            // Check if columns don't exist before adding them
            if (!Schema::hasColumn('medecin_profiles', 'moyens_paiement')) {
                $table->json('moyens_paiement')->nullable();
            }
            if (!Schema::hasColumn('medecin_profiles', 'moyens_transport')) {
                $table->json('moyens_transport')->nullable();
            }
            if (!Schema::hasColumn('medecin_profiles', 'informations_pratiques')) {
                $table->text('informations_pratiques')->nullable();
            }
            if (!Schema::hasColumn('medecin_profiles', 'jours_disponibles')) {
                $table->json('jours_disponibles')->nullable();
            }
            if (!Schema::hasColumn('medecin_profiles', 'contact_urgence')) {
                $table->string('contact_urgence')->nullable();
            }
            if (!Schema::hasColumn('medecin_profiles', 'rdv_patients_suivis_uniquement')) {
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
        Schema::table('medecin_profiles', function (Blueprint $table) {
            $table->dropColumn([
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
