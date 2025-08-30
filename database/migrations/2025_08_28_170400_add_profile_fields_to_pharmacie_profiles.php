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
        Schema::table('pharmacie_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('pharmacie_profiles', 'ville')) {
                $table->string('ville')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'horaire_start')) {
                $table->string('horaire_start')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'horaire_end')) {
                $table->string('horaire_end')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'presentation')) {
                $table->text('presentation')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'additional_info')) {
                $table->text('additional_info')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'services')) {
                $table->json('services')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'moyens_paiement')) {
                $table->json('moyens_paiement')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'moyens_transport')) {
                $table->json('moyens_transport')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'informations_pratiques')) {
                $table->text('informations_pratiques')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'jours_disponibles')) {
                $table->json('jours_disponibles')->nullable();
            }
            if (!Schema::hasColumn('pharmacie_profiles', 'contact_urgence')) {
                $table->string('contact_urgence')->nullable();
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
        Schema::table('pharmacie_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'ville',
                'horaire_start',
                'horaire_end',
                'presentation',
                'additional_info',
                'services',
                'moyens_paiement',
                'moyens_transport',
                'informations_pratiques',
                'jours_disponibles',
                'contact_urgence'
            ]);
        });
    }
};
