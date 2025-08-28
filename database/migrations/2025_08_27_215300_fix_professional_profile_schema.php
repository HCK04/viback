<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FixProfessionalProfileSchema extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Fix medecin_profiles - remove inappropriate org fields, fix diplomas duplication
        if (Schema::hasTable('medecin_profiles')) {
            Schema::table('medecin_profiles', function (Blueprint $table) {
                // Drop inappropriate organization fields for doctors
                if (Schema::hasColumn('medecin_profiles', 'org_presentation')) {
                    $table->dropColumn('org_presentation');
                }
                if (Schema::hasColumn('medecin_profiles', 'services_description')) {
                    $table->dropColumn('services_description');
                }
                if (Schema::hasColumn('medecin_profiles', 'vacation_mode')) {
                    $table->dropColumn('vacation_mode');
                }
                if (Schema::hasColumn('medecin_profiles', 'vacation_auto_reactivate_date')) {
                    $table->dropColumn('vacation_auto_reactivate_date');
                }
                
                // Keep only diplomes (French) field, drop duplicate diplomas (English)
                if (Schema::hasColumn('medecin_profiles', 'diplomas')) {
                    $table->dropColumn('diplomas');
                }
                
                // Ensure we have the correct fields for doctors
                if (!Schema::hasColumn('medecin_profiles', 'diplomes')) {
                    $table->json('diplomes')->nullable();
                }
                if (!Schema::hasColumn('medecin_profiles', 'experiences')) {
                    $table->json('experiences')->nullable();
                }
            });
        }

        // Fix kine_profiles - remove inappropriate org fields, fix diplomas duplication
        if (Schema::hasTable('kine_profiles')) {
            Schema::table('kine_profiles', function (Blueprint $table) {
                // Drop inappropriate organization fields
                if (Schema::hasColumn('kine_profiles', 'org_presentation')) {
                    $table->dropColumn('org_presentation');
                }
                if (Schema::hasColumn('kine_profiles', 'services_description')) {
                    $table->dropColumn('services_description');
                }
                if (Schema::hasColumn('kine_profiles', 'vacation_mode')) {
                    $table->dropColumn('vacation_mode');
                }
                if (Schema::hasColumn('kine_profiles', 'vacation_auto_reactivate_date')) {
                    $table->dropColumn('vacation_auto_reactivate_date');
                }
                
                // Keep only diplomes (French) field, drop duplicate diplomas (English)
                if (Schema::hasColumn('kine_profiles', 'diplomas')) {
                    $table->dropColumn('diplomas');
                }
                
                // Ensure we have the correct fields
                if (!Schema::hasColumn('kine_profiles', 'diplomes')) {
                    $table->json('diplomes')->nullable();
                }
                if (!Schema::hasColumn('kine_profiles', 'experiences')) {
                    $table->json('experiences')->nullable();
                }
            });
        }

        // Fix orthophoniste_profiles - remove inappropriate org fields, fix diplomas duplication
        if (Schema::hasTable('orthophoniste_profiles')) {
            Schema::table('orthophoniste_profiles', function (Blueprint $table) {
                // Drop inappropriate organization fields
                if (Schema::hasColumn('orthophoniste_profiles', 'org_presentation')) {
                    $table->dropColumn('org_presentation');
                }
                if (Schema::hasColumn('orthophoniste_profiles', 'services_description')) {
                    $table->dropColumn('services_description');
                }
                if (Schema::hasColumn('orthophoniste_profiles', 'vacation_mode')) {
                    $table->dropColumn('vacation_mode');
                }
                if (Schema::hasColumn('orthophoniste_profiles', 'vacation_auto_reactivate_date')) {
                    $table->dropColumn('vacation_auto_reactivate_date');
                }
                
                // Keep only diplomes (French) field, drop duplicate diplomas (English)
                if (Schema::hasColumn('orthophoniste_profiles', 'diplomas')) {
                    $table->dropColumn('diplomas');
                }
                
                // Ensure we have the correct fields
                if (!Schema::hasColumn('orthophoniste_profiles', 'diplomes')) {
                    $table->json('diplomes')->nullable();
                }
                if (!Schema::hasColumn('orthophoniste_profiles', 'experiences')) {
                    $table->json('experiences')->nullable();
                }
            });
        }

        // Fix psychologue_profiles - remove inappropriate org fields, fix diplomas duplication
        if (Schema::hasTable('psychologue_profiles')) {
            Schema::table('psychologue_profiles', function (Blueprint $table) {
                // Drop inappropriate organization fields
                if (Schema::hasColumn('psychologue_profiles', 'org_presentation')) {
                    $table->dropColumn('org_presentation');
                }
                if (Schema::hasColumn('psychologue_profiles', 'services_description')) {
                    $table->dropColumn('services_description');
                }
                if (Schema::hasColumn('psychologue_profiles', 'vacation_mode')) {
                    $table->dropColumn('vacation_mode');
                }
                if (Schema::hasColumn('psychologue_profiles', 'vacation_auto_reactivate_date')) {
                    $table->dropColumn('vacation_auto_reactivate_date');
                }
                
                // Keep only diplomes (French) field, drop duplicate diplomas (English)
                if (Schema::hasColumn('psychologue_profiles', 'diplomas')) {
                    $table->dropColumn('diplomas');
                }
                
                // Ensure we have the correct fields
                if (!Schema::hasColumn('psychologue_profiles', 'diplomes')) {
                    $table->json('diplomes')->nullable();
                }
                if (!Schema::hasColumn('psychologue_profiles', 'experiences')) {
                    $table->json('experiences')->nullable();
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
        // Reverse the changes if needed
    }
}
