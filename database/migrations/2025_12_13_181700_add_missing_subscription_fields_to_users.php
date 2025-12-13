<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Only add columns if they don't exist
            if (!Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->string('stripe_customer_id')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'subscription_type')) {
                $table->enum('subscription_type', ['basic', 'family'])->nullable()->after('is_subscribed');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'stripe_customer_id')) {
                $table->dropColumn('stripe_customer_id');
            }
            if (Schema::hasColumn('users', 'subscription_type')) {
                $table->dropColumn('subscription_type');
            }
        });
    }
};
