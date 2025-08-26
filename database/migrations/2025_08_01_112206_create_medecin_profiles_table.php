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
        Schema::create('medecin_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('specialty', 100)->nullable();
            $table->integer('experience_years')->nullable();
            $table->string('horaires', 100)->nullable();
            $table->json('diplomas')->nullable();
            $table->string('adresse', 255)->nullable();
            $table->boolean('disponible')->default(true);
            $table->date('absence_start_date')->nullable();
            $table->date('absence_end_date')->nullable();
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('medecin_profiles');
    }
};
