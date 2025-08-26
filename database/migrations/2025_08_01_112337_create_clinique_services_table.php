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
        Schema::create('clinique_services', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('clinique_id');
            $table->unsignedBigInteger('service_id');
            $table->timestamps();
            $table->foreign('clinique_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('clinique_services');
    }
};
