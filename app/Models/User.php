<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role_id',
        'is_verified',
        'is_subscribed',
        'subscription_type',
        'stripe_customer_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * Get the role associated with the user.
     */
    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the patient profile associated with the user.
     */
    public function patientProfile()
    {
        return $this->hasOne(PatientProfile::class);
    }

    /**
     * Get the medecin profile associated with the user.
     */
    public function medecinProfile()
    {
        return $this->hasOne(MedecinProfile::class);
    }

    /**
     * Get the profile associated with the user.
     */
    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    /**
     * Get the subscription associated with the user.
     */
    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    /**
     * Get family members (for family plan users)
     */
    public function familyMembers()
    {
        return $this->hasMany(FamilyMember::class, 'primary_user_id');
    }

    /**
     * Check if user has active subscription
     */
    public function hasActiveSubscription()
    {
        return $this->is_subscribed && $this->subscription && $this->subscription->isActive();
    }

    /**
     * Check if user can add family members
     */
    public function canAddFamilyMembers()
    {
        return $this->subscription_type === 'family' && $this->hasActiveSubscription();
    }

    /**
     * Get maximum family members allowed
     */
    public function getMaxFamilyMembers()
    {
        return $this->subscription_type === 'family' ? 5 : 0;
    }

    /**
     * Get the appointments (RDV) associated with the user.
     */
    public function rdvs()
    {
        return $this->hasMany(Rdv::class, 'user_id');
    }

    /**
     * Get the announcements (annonces) associated with the user.
     */
    public function annonces()
    {
        return $this->hasMany(Annonce::class, 'user_id');
    }
}
