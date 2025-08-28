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
        // Add CV fields to medecin_profiles table
        Schema::table('medecin_profiles', function (Blueprint $table) {
            $table->text('presentation')->nullable();
            $table->string('carte_professionnelle')->nullable();
            $table->json('diplomes')->nullable();
            $table->json('experiences')->nullable();
        });

        // Add CV fields to kine_profiles table
        Schema::table('kine_profiles', function (Blueprint $table) {
            $table->text('presentation')->nullable();
            $table->string('carte_professionnelle')->nullable();
            $table->json('diplomes')->nullable();
            $table->json('experiences')->nullable();
        });

        // Add CV fields to orthophoniste_profiles table
        Schema::table('orthophoniste_profiles', function (Blueprint $table) {
            $table->text('presentation')->nullable();
            $table->string('carte_professionnelle')->nullable();
            $table->json('diplomes')->nullable();
            $table->json('experiences')->nullable();
        });

        // Add CV fields to psychologue_profiles table
        Schema::table('psychologue_profiles', function (Blueprint $table) {
            $table->text('presentation')->nullable();
            $table->string('carte_professionnelle')->nullable();
            $table->json('diplomes')->nullable();
            $table->json('experiences')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove CV fields from medecin_profiles table
        Schema::table('medecin_profiles', function (Blueprint $table) {
            $table->dropColumn(['presentation', 'carte_professionnelle', 'diplomes', 'experiences']);
        });

        // Remove CV fields from kine_profiles table
        Schema::table('kine_profiles', function (Blueprint $table) {
            $table->dropColumn(['presentation', 'carte_professionnelle', 'diplomes', 'experiences']);
        });

        // Remove CV fields from orthophoniste_profiles table
        Schema::table('orthophoniste_profiles', function (Blueprint $table) {
            $table->dropColumn(['presentation', 'carte_professionnelle', 'diplomes', 'experiences']);
        });

        // Remove CV fields from psychologue_profiles table
        Schema::table('psychologue_profiles', function (Blueprint $table) {
            $table->dropColumn(['presentation', 'carte_professionnelle', 'diplomes', 'experiences']);
        });
    }
};
