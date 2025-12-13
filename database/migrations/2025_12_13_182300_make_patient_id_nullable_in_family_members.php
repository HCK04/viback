<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make patient_id nullable using raw SQL
        DB::statement('ALTER TABLE family_members MODIFY patient_id BIGINT UNSIGNED NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE family_members MODIFY patient_id BIGINT UNSIGNED NOT NULL');
    }
};
