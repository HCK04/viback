<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clinique_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('clinique_profiles', 'clinic_presentation')) {
                $table->text('clinic_presentation')->nullable();
            }
            if (!Schema::hasColumn('clinique_profiles', 'clinic_services_description')) {
                $table->text('clinic_services_description')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clinique_profiles', function (Blueprint $table) {
            $table->dropColumn(['clinic_presentation', 'clinic_services_description']);
        });
    }
};
