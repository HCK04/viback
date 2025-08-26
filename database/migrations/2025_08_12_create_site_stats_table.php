<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('site_stats', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('baseline')->default(12000);
            $table->dateTime('start_time');
            $table->timestamps();
        });
        
        // Insert initial record
        DB::table('site_stats')->insert([
            'baseline' => 12000,
            'start_time' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('site_stats');
    }
};
