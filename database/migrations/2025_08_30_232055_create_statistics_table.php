<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('statistics', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'patients', 'doctors', 'pharmacies'
            $table->integer('baseline')->default(0); // Starting number
            $table->timestamp('start_time'); // When auto-increment started
            $table->timestamps();
            
            $table->unique('type'); // Only one record per type
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistics');
    }
};
