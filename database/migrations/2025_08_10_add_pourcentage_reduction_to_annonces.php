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
        // Check if the column doesn't exist already
        if (!Schema::hasColumn('annonces', 'pourcentage_reduction')) {
            Schema::table('annonces', function (Blueprint $table) {
                $table->integer('pourcentage_reduction')->default(0)->after('is_active');
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
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn('pourcentage_reduction');
        });
    }
};
