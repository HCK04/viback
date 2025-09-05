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
        Schema::table('centre_radiologie_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('centre_radiologie_profiles', 'ville')) {
                $table->string('ville')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'horaire_start')) {
                $table->string('horaire_start')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'horaire_end')) {
                $table->string('horaire_end')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'presentation')) {
                $table->text('presentation')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'additional_info')) {
                $table->text('additional_info')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'moyens_paiement')) {
                $table->json('moyens_paiement')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'moyens_transport')) {
                $table->json('moyens_transport')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'informations_pratiques')) {
                $table->text('informations_pratiques')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'jours_disponibles')) {
                $table->json('jours_disponibles')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'contact_urgence')) {
                $table->string('contact_urgence')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'org_presentation')) {
                $table->text('org_presentation')->nullable();
            }
            if (!Schema::hasColumn('centre_radiologie_profiles', 'services_description')) {
                $table->text('services_description')->nullable();
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
        Schema::table('centre_radiologie_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'ville',
                'horaire_start',
                'horaire_end',
                'presentation',
                'additional_info',
                'moyens_paiement',
                'moyens_transport',
                'informations_pratiques',
                'jours_disponibles',
                'contact_urgence'
            ]);
        });
    }
};
