<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stripe_subscription_id',
        'stripe_customer_id',
        'plan_id',
        'plan_type',
        'status',
        'amount',
        'billing_interval',
        'current_period_start',
        'current_period_end',
        'trial_end',
        'cancel_at_period_end',
        'canceled_at'
    ];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_end' => 'datetime',
        'canceled_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'amount' => 'decimal:2'
    ];

    /**
     * Get the user that owns the subscription
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if subscription is active
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * Check if subscription is canceled
     */
    public function isCanceled()
    {
        return $this->status === 'canceled' || $this->cancel_at_period_end;
    }

    /**
     * Check if subscription is past due
     */
    public function isPastDue()
    {
        return $this->status === 'past_due';
    }

    /**
     * Check if subscription allows family members
     */
    public function allowsFamilyMembers()
    {
        return $this->plan_type === 'family' && $this->isActive();
    }

    /**
     * Get maximum family members allowed
     */
    public function getMaxFamilyMembers()
    {
        return $this->plan_type === 'family' ? 5 : 0;
    }

    /**
     * Get subscription status badge
     */
    public function getStatusBadge()
    {
        $badges = [
            'active' => ['color' => 'green', 'text' => 'Actif'],
            'canceled' => ['color' => 'red', 'text' => 'Annulé'],
            'past_due' => ['color' => 'yellow', 'text' => 'Impayé'],
            'incomplete' => ['color' => 'gray', 'text' => 'Incomplet'],
            'trialing' => ['color' => 'blue', 'text' => 'Essai']
        ];

        return $badges[$this->status] ?? ['color' => 'gray', 'text' => 'Inconnu'];
    }
}
