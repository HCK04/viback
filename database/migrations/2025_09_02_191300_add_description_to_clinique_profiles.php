<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDescriptionToCliniqueProfiles extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('clinique_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('clinique_profiles', 'description')) {
                $table->text('description')->nullable()->after('services');
            }
            if (!Schema::hasColumn('clinique_profiles', 'etablissement_image')) {
                $table->string('etablissement_image')->nullable()->after('description');
            }
            if (!Schema::hasColumn('clinique_profiles', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('etablissement_image');
            }
            if (!Schema::hasColumn('clinique_profiles', 'rating')) {
                $table->decimal('rating', 3, 2)->default(0.0)->after('profile_image');
            }
            if (!Schema::hasColumn('clinique_profiles', 'vacation_mode')) {
                $table->boolean('vacation_mode')->default(false)->after('rating');
            }
            if (!Schema::hasColumn('clinique_profiles', 'vacation_auto_reactivate_date')) {
                $table->date('vacation_auto_reactivate_date')->nullable()->after('vacation_mode');
            }
            if (!Schema::hasColumn('clinique_profiles', 'gallery')) {
                $table->json('gallery')->nullable()->after('vacation_auto_reactivate_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('clinique_profiles', function (Blueprint $table) {
            $table->dropColumn(['description', 'etablissement_image', 'profile_image', 'rating', 'vacation_mode', 'vacation_auto_reactivate_date', 'gallery']);
        });
    }
}
