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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('stripe_subscription_id')->unique();
            $table->string('stripe_customer_id');
            $table->string('plan_id'); // basic_monthly, family_monthly, etc.
            $table->enum('plan_type', ['basic', 'family']);
            $table->enum('status', ['active', 'canceled', 'past_due', 'incomplete', 'trialing']);
            $table->decimal('amount', 8, 2); // Amount in DH
            $table->enum('billing_interval', ['month', 'year']);
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('stripe_subscription_id');
            $table->index('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
