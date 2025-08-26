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
        Schema::create('patient_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->unique();
            $table->integer('age')->nullable();
            $table->enum('gender', ['homme','femme'])->nullable();
            $table->string('blood_type', 3)->nullable();
            $table->text('allergies')->nullable();
            $table->text('chronic_diseases')->nullable();
            $table->integer('missed_rdv')->default(0);
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
        Schema::dropIfExists('patient_profiles');
    }
};
