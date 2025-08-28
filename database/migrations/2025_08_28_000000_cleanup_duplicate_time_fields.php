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
        // Clean up duplicate time fields in medecin_profiles
        if (Schema::hasTable('medecin_profiles')) {
            Schema::table('medecin_profiles', function (Blueprint $table) {
                // Remove JSON horaires field - we'll use horaire_start/horaire_end instead
                if (Schema::hasColumn('medecin_profiles', 'horaires')) {
                    $table->dropColumn('horaires');
                }
                
                // Remove start_time/end_time if they exist - we'll use horaire_start/horaire_end
                if (Schema::hasColumn('medecin_profiles', 'start_time')) {
                    $table->dropColumn('start_time');
                }
                if (Schema::hasColumn('medecin_profiles', 'end_time')) {
                    $table->dropColumn('end_time');
                }
            });
        }

        // Clean up duplicate time fields in kine_profiles
        if (Schema::hasTable('kine_profiles')) {
            Schema::table('kine_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('kine_profiles', 'horaires')) {
                    $table->dropColumn('horaires');
                }
                if (Schema::hasColumn('kine_profiles', 'start_time')) {
                    $table->dropColumn('start_time');
                }
                if (Schema::hasColumn('kine_profiles', 'end_time')) {
                    $table->dropColumn('end_time');
                }
            });
        }

        // Clean up duplicate time fields in orthophoniste_profiles
        if (Schema::hasTable('orthophoniste_profiles')) {
            Schema::table('orthophoniste_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('orthophoniste_profiles', 'horaires')) {
                    $table->dropColumn('horaires');
                }
                if (Schema::hasColumn('orthophoniste_profiles', 'start_time')) {
                    $table->dropColumn('start_time');
                }
                if (Schema::hasColumn('orthophoniste_profiles', 'end_time')) {
                    $table->dropColumn('end_time');
                }
            });
        }

        // Clean up duplicate time fields in psychologue_profiles
        if (Schema::hasTable('psychologue_profiles')) {
            Schema::table('psychologue_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('psychologue_profiles', 'horaires')) {
                    $table->dropColumn('horaires');
                }
                if (Schema::hasColumn('psychologue_profiles', 'start_time')) {
                    $table->dropColumn('start_time');
                }
                if (Schema::hasColumn('psychologue_profiles', 'end_time')) {
                    $table->dropColumn('end_time');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add back the removed fields if needed
        if (Schema::hasTable('medecin_profiles')) {
            Schema::table('medecin_profiles', function (Blueprint $table) {
                $table->json('horaires')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
            });
        }

        if (Schema::hasTable('kine_profiles')) {
            Schema::table('kine_profiles', function (Blueprint $table) {
                $table->json('horaires')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
            });
        }

        if (Schema::hasTable('orthophoniste_profiles')) {
            Schema::table('orthophoniste_profiles', function (Blueprint $table) {
                $table->json('horaires')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
            });
        }

        if (Schema::hasTable('psychologue_profiles')) {
            Schema::table('psychologue_profiles', function (Blueprint $table) {
                $table->json('horaires')->nullable();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
            });
        }
    }
};
