<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->string('name')->nullable()->after('patient_id');
            $table->string('relationship')->nullable()->after('name');
            $table->unsignedTinyInteger('age')->nullable()->after('relationship');
            $table->string('email')->nullable()->after('age');
            $table->string('phone')->nullable()->after('email');
            $table->string('blood_type')->nullable()->after('phone');
            $table->decimal('discount_percentage', 5, 2)->default(10.00)->after('blood_type');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('family_members', function (Blueprint $table) {
            $table->dropColumn([
                'name',
                'relationship',
                'age',
                'email',
                'phone',
                'blood_type',
                'discount_percentage'
            ]);
        });
    }
};
