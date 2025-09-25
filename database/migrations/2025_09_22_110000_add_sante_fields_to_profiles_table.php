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
        Schema::table('patient_profiles', function (Blueprint $table) {
            // JSON arrays for Santé sections
            $table->json('sante_documents')->nullable();
            $table->json('sante_antecedents_medicaux')->nullable();
            $table->json('sante_traitements_reguliers')->nullable();
            $table->json('sante_allergies')->nullable();
            $table->json('sante_antecedents_familiaux')->nullable();
            $table->json('sante_operations_chirurgicales')->nullable();
            $table->json('sante_vaccins')->nullable();
            $table->json('sante_mesures')->nullable();

            // NONE flags per section
            $table->boolean('sante_documents_none')->default(false);
            $table->boolean('sante_antecedents_medicaux_none')->default(false);
            $table->boolean('sante_traitements_reguliers_none')->default(false);
            $table->boolean('sante_allergies_none')->default(false);
            $table->boolean('sante_antecedents_familiaux_none')->default(false);
            $table->boolean('sante_operations_chirurgicales_none')->default(false);
            $table->boolean('sante_vaccins_none')->default(false);
            $table->boolean('sante_mesures_none')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('patient_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'sante_documents',
                'sante_antecedents_medicaux',
                'sante_traitements_reguliers',
                'sante_allergies',
                'sante_antecedents_familiaux',
                'sante_operations_chirurgicales',
                'sante_vaccins',
                'sante_mesures',
                'sante_documents_none',
                'sante_antecedents_medicaux_none',
                'sante_traitements_reguliers_none',
                'sante_allergies_none',
                'sante_antecedents_familiaux_none',
                'sante_operations_chirurgicales_none',
                'sante_vaccins_none',
                'sante_mesures_none',
            ]);
        });
    }
};
