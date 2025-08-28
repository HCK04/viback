<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnsureMedecinProfilesComplete extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('medecin_profiles')) {
            Schema::table('medecin_profiles', function (Blueprint $table) {
                // Ensure all frontend fields exist
                if (!Schema::hasColumn('medecin_profiles', 'presentation')) {
                    $table->text('presentation')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'diplomes')) {
                    $table->json('diplomes')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'experiences')) {
                    $table->json('experiences')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'carte_professionnelle')) {
                    $table->string('carte_professionnelle')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Reverse if needed
    }
}
