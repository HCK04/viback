<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('medecin_profiles', function (Blueprint $table) {
            $table->json('moyens_paiement')->nullable()->after('experiences');
            $table->json('moyens_transport')->nullable()->after('moyens_paiement');
            $table->text('informations_pratiques')->nullable()->after('moyens_transport');
            $table->json('jours_disponibles')->nullable()->after('informations_pratiques');
            $table->text('contact_urgence')->nullable()->after('jours_disponibles');
            $table->boolean('rdv_patients_suivis_uniquement')->default(false)->after('contact_urgence');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
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
