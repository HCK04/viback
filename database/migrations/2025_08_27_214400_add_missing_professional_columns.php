<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMissingProfessionalColumns extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Add missing columns to medecin_profiles
        if (Schema::hasTable('medecin_profiles')) {
            Schema::table('medecin_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('medecin_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('medecin_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('carte_professionnelle');
                }
                if (!Schema::hasColumn('medecin_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('profile_image');
                }
                if (!Schema::hasColumn('medecin_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('presentation');
                }
                if (!Schema::hasColumn('medecin_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('medecin_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('medecin_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('medecin_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('medecin_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable()->after('horaires');
                }
                if (!Schema::hasColumn('medecin_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable()->after('horaire_start');
                }
            });
        }

        // Add missing columns to kine_profiles
        if (Schema::hasTable('kine_profiles')) {
            Schema::table('kine_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('kine_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('kine_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('carte_professionnelle');
                }
                if (!Schema::hasColumn('kine_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('profile_image');
                }
                if (!Schema::hasColumn('kine_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('presentation');
                }
                if (!Schema::hasColumn('kine_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('kine_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('kine_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('kine_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('kine_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable()->after('horaires');
                }
                if (!Schema::hasColumn('kine_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable()->after('horaire_start');
                }
                if (!Schema::hasColumn('kine_profiles', 'specialty')) {
                    $table->json('specialty')->nullable()->after('experience_years');
                }
            });
        }

        // Add missing columns to orthophoniste_profiles
        if (Schema::hasTable('orthophoniste_profiles')) {
            Schema::table('orthophoniste_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('orthophoniste_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('carte_professionnelle');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('profile_image');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('presentation');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable()->after('horaires');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable()->after('horaire_start');
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'specialty')) {
                    $table->json('specialty')->nullable()->after('experience_years');
                }
            });
        }

        // Add missing columns to psychologue_profiles
        if (Schema::hasTable('psychologue_profiles')) {
            Schema::table('psychologue_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('psychologue_profiles', 'ville')) {
                    $table->string('ville', 100)->nullable()->after('adresse');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'profile_image')) {
                    $table->string('profile_image')->nullable()->after('carte_professionnelle');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'rating')) {
                    $table->decimal('rating', 2, 1)->nullable()->default(0)->after('profile_image');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'org_presentation')) {
                    $table->text('org_presentation')->nullable()->after('presentation');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'services_description')) {
                    $table->text('services_description')->nullable()->after('org_presentation');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'additional_info')) {
                    $table->text('additional_info')->nullable()->after('services_description');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'vacation_mode')) {
                    $table->boolean('vacation_mode')->default(false)->after('additional_info');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'vacation_auto_reactivate_date')) {
                    $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'horaire_start')) {
                    $table->time('horaire_start')->nullable()->after('horaires');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'horaire_end')) {
                    $table->time('horaire_end')->nullable()->after('horaire_start');
                }
                if (!Schema::hasColumn('psychologue_profiles', 'specialty')) {
                    $table->json('specialty')->nullable()->after('experience_years');
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
        $tables = [
            'medecin_profiles',
            'kine_profiles', 
            'orthophoniste_profiles',
            'psychologue_profiles'
        ];

        $columns = [
            'ville', 'profile_image', 'rating', 'org_presentation', 
            'services_description', 'additional_info', 'vacation_mode', 
            'vacation_auto_reactivate_date', 'horaire_start', 'horaire_end', 'specialty'
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) use ($columns) {
                    foreach ($columns as $column) {
                        if (Schema::hasColumn($table->getTable(), $column)) {
                            $table->dropColumn($column);
                        }
                    }
                });
            }
        }
    }
}
