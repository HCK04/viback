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
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 100);
            $table->unsignedTinyInteger('role_id')->nullable();
            $table->enum('plan_type', ['individual','family-2','family-5','family-8','organization']);
            $table->enum('duration', ['monthly','yearly']);
            $table->decimal('amount', 8, 2);
            $table->timestamps();
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('subscription_plans');
    }
};
