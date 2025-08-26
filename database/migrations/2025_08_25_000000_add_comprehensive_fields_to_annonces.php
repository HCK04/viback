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
        Schema::table('annonces', function (Blueprint $table) {
            $table->text('content')->nullable()->after('description');
            $table->string('type')->default('general')->after('content');
            $table->string('category')->nullable()->after('type');
            $table->integer('duration')->nullable()->after('category'); // in minutes
            $table->string('location')->nullable()->after('duration');
            $table->json('availability')->nullable()->after('location');
            $table->integer('views_count')->default(0)->after('availability');
            $table->integer('rdv_count')->default(0)->after('views_count');
            $table->timestamp('published_at')->nullable()->after('rdv_count');
            $table->string('status')->default('active')->after('published_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('annonces', function (Blueprint $table) {
            $table->dropColumn([
                'content',
                'type', 
                'category',
                'duration',
                'location',
                'availability',
                'views_count',
                'rdv_count',
                'published_at',
                'status'
            ]);
        });
    }
};
