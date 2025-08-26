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
        Schema::table('rdv', function (Blueprint $table) {
            $table->unsignedBigInteger('annonce_id')->nullable()->after('target_user_id');
            $table->foreign('annonce_id')->references('id')->on('annonces')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('rdv', function (Blueprint $table) {
            $table->dropForeign(['annonce_id']);
            $table->dropColumn('annonce_id');
        });
    }
};
