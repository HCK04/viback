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
        Schema::table('medecin_profiles', function (Blueprint $table) {
            $table->string('numero_carte_professionnelle')->nullable()->after('carte_professionnelle');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('medecin_profiles', function (Blueprint $table) {
            $table->dropColumn('numero_carte_professionnelle');
        });
    }
};
