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
        Schema::table('parapharmacie_profiles', function (Blueprint $table) {
            $table->string('ville')->nullable();
            $table->string('horaire_start')->nullable();
            $table->string('horaire_end')->nullable();
            $table->text('presentation')->nullable();
            $table->text('additional_info')->nullable();
            $table->json('services')->nullable();
            
            // New profile fields
            $table->json('moyens_paiement')->nullable();
            $table->json('moyens_transport')->nullable();
            $table->text('informations_pratiques')->nullable();
            $table->json('jours_disponibles')->nullable();
            $table->string('contact_urgence')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('parapharmacie_profiles', function (Blueprint $table) {
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
