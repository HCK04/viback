<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('kine_profiles')) {
            Schema::table('kine_profiles', function (Blueprint $table) {
                // Common descriptive fields
                if (!Schema::hasColumn('kine_profiles', 'presentation')) {
                    $table->text('presentation')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }

                // Documents / images
                if (!Schema::hasColumn('kine_profiles', 'carte_professionnelle')) {
                    $table->string('carte_professionnelle')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'imgs')) {
                    $table->json('imgs')->nullable();
                }

                // CV / JSON fields
                if (!Schema::hasColumn('kine_profiles', 'diplomes')) {
                    $table->json('diplomes')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'experiences')) {
                    $table->json('experiences')->nullable();
                }

                // Practical information & access/payment/working days
                if (!Schema::hasColumn('kine_profiles', 'moyens_transport')) {
                    $table->json('moyens_transport')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'moyens_paiement')) {
                    $table->json('moyens_paiement')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'jours_disponibles')) {
                    $table->json('jours_disponibles')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'informations_pratiques')) {
                    $table->text('informations_pratiques')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'contact_urgence')) {
                    $table->string('contact_urgence')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No destructive down by default to avoid data loss
    }
};
