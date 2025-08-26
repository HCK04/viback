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
            // Drop auto-increment and change ID to string
            $table->dropPrimary();
            $table->string('id', 16)->primary()->change();
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
            // Revert back to auto-increment integer
            $table->dropPrimary();
            $table->bigIncrements('id')->change();
        });
    }
};
