<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingColumnsOnly extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add missing columns to clinique_profiles
        if (Schema::hasTable('clinique_profiles')) {
            Schema::table('clinique_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('clinique_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'etablissement_image')) {
                    $table->string('etablissement_image')->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }
                if (!Schema::hasColumn('clinique_profiles', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'services_description')) {
                    $table->text('services_description')->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false);
                }
                if (!Schema::hasColumn('clinique_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'gallery')) {
                    $table->json('gallery')->nullable();
                }
                if (!Schema::hasColumn('clinique_profiles', 'responsable_name')) {
                    $table->string('responsable_name', 100)->nullable();
                }
            });
        }

        // Add missing columns to pharmacie_profiles
        if (Schema::hasTable('pharmacie_profiles')) {
            Schema::table('pharmacie_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('pharmacie_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'services')) {
                    $table->json('services')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'etablissement_image')) {
                    $table->string('etablissement_image')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'services_description')) {
                    $table->text('services_description')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false);
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'gallery')) {
                    $table->json('gallery')->nullable();
                }
                if (!Schema::hasColumn('pharmacie_profiles', 'responsable_name')) {
                    $table->string('responsable_name', 100)->nullable();
                }
            });
        }

        // Add missing columns to labo_analyse_profiles
        if (Schema::hasTable('labo_analyse_profiles')) {
            Schema::table('labo_analyse_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('labo_analyse_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'etablissement_image')) {
                    $table->string('etablissement_image')->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'description')) {
                    $table->text('description')->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'services_description')) {
                    $table->text('services_description')->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false);
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'gallery')) {
                    $table->json('gallery')->nullable();
                }
                if (!Schema::hasColumn('labo_analyse_profiles', 'responsable_name')) {
                    $table->string('responsable_name', 100)->nullable();
                }
            });
        }

        // Add missing columns to medecin_profiles
        if (Schema::hasTable('medecin_profiles')) {
            Schema::table('medecin_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('medecin_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }
                if (!Schema::hasColumn('medecin_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'services_description')) {
                    $table->text('services_description')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false);
                }
                if (!Schema::hasColumn('medecin_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable();
                }
            });
        }

        // Add missing columns to kine_profiles
        if (Schema::hasTable('kine_profiles')) {
            Schema::table('kine_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('kine_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }
                if (!Schema::hasColumn('kine_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'services_description')) {
                    $table->text('services_description')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false);
                }
                if (!Schema::hasColumn('kine_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'specialty')) {
                    $table->json('specialty')->nullable();
                }
            });
        }

        // Add missing columns to orthophoniste_profiles
        if (Schema::hasTable('orthophoniste_profiles')) {
            Schema::table('orthophoniste_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('orthophoniste_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'services_description')) {
                    $table->text('services_description')->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false);
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'specialty')) {
                    $table->json('specialty')->nullable();
                }
            });
        }

        // Add missing columns to psychologue_profiles
        if (Schema::hasTable('psychologue_profiles')) {
            Schema::table('psychologue_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('psychologue_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0);
                }
                if (!Schema::hasColumn('psychologue_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'services_description')) {
                    $table->text('services_description')->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false);
                }
                if (!Schema::hasColumn('psychologue_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'specialty')) {
                    $table->json('specialty')->nullable();
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
        // Reverse migration logic here if needed
    }
}
