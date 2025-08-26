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
        // Check if column doesn't exist before adding it
        if (!Schema::hasColumn('rdv', 'annonce_id')) {
            Schema::table('rdv', function (Blueprint $table) {
                $table->unsignedBigInteger('annonce_id')->nullable()->after('target_role');
                $table->foreign('annonce_id')->references('id')->on('annonces')->onDelete('set null');
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
        if (Schema::hasColumn('rdv', 'annonce_id')) {
            Schema::table('rdv', function (Blueprint $table) {
                $table->dropForeign(['annonce_id']);
                $table->dropColumn('annonce_id');
            });
        }
    }
};
