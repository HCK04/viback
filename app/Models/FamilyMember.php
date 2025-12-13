<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FamilyMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'subscription_id',
        'patient_id',
        'name',
        'relationship',
        'age',
        'email',
        'phone',
        'blood_type',
        'discount_percentage'
    ];

    protected $casts = [
        'age' => 'integer',
        'discount_percentage' => 'decimal:2'
    ];

    /**
     * Get the subscription this family member belongs to
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the patient user if linked
     */
    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    /**
     * Get the primary user (subscription owner)
     */
    public function primaryUser()
    {
        return $this->hasOneThrough(
            User::class,
            Subscription::class,
            'id',           // subscriptions.id
            'id',           // users.id
            'subscription_id', // family_members.subscription_id
            'user_id'       // subscriptions.user_id
        );
    }

    /**
     * Get translated relationship name
     */
    public function getRelationshipLabelAttribute()
    {
        $labels = [
            'conjoint' => 'Conjoint(e)',
            'enfant' => 'Enfant',
            'parent' => 'Parent',
            'frère/sœur' => 'Frère/Sœur',
            'autre' => 'Autre'
        ];

        return $labels[$this->relationship] ?? $this->relationship;
    }

    /**
     * Get the applicable discount percentage (default 10%)
     */
    public function getApplicableDiscount()
    {
        return $this->discount_percentage ?? 10.00;
    }
}
