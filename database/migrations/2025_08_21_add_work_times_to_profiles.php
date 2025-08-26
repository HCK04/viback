<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Add start_time and end_time to medecin_profiles
        Schema::table('medecin_profiles', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('horaires');
            $table->time('end_time')->nullable()->after('start_time');
        });

        // Add start_time and end_time to kine_profiles
        Schema::table('kine_profiles', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('horaires');
            $table->time('end_time')->nullable()->after('start_time');
        });

        // Add start_time and end_time to orthophoniste_profiles
        Schema::table('orthophoniste_profiles', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('horaires');
            $table->time('end_time')->nullable()->after('start_time');
        });

        // Add start_time and end_time to psychologue_profiles
        Schema::table('psychologue_profiles', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('horaires');
            $table->time('end_time')->nullable()->after('start_time');
        });
    }

    public function down()
    {
        Schema::table('medecin_profiles', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });

        Schema::table('kine_profiles', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });

        Schema::table('orthophoniste_profiles', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });

        Schema::table('psychologue_profiles', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
