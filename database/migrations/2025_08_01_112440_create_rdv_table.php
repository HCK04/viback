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
        Schema::create('rdv', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('patient_id');
            $table->unsignedBigInteger('target_user_id');
            $table->enum('target_role', [
                'medecin', 'clinique', 'pharmacie', 'parapharmacie',
                'centre_radiologie', 'kine', 'labo_analyse', 'orthophoniste', 'psychologue', 'autre'
            ]);
            $table->dateTime('date_time');
            // updated to match frontend
            $table->enum('status', ['pending','confirmed','cancelled','completed','missed'])->default('pending');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('target_user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rdv');
    }
};
