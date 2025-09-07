<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PharmacieProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_pharmacie',
        'adresse',
        'ville',
        'horaires',
        'horaire_start',
        'horaire_end',
        'gerant_name',
        'disponible',
        'absence_start_date',
        'absence_end_date',
        'presentation',
        'additional_info',
        'services',
        'profile_image',
        'etablissement_image',
        'rating',
        'description',
        'org_presentation',
        'services_description',
        'vacation_mode',
        'vacation_auto_reactivate_date',
        'gallery',
        'imgs',
        'responsable_name',
        'guard',
        'moyens_paiement',
        'moyens_transport',
        'informations_pratiques',
        'jours_disponibles',
        'contact_urgence'
    ];
    
    // Add this to handle JSON data
    protected $casts = [
        'horaires' => 'array',
        'services' => 'array',
        'moyens_paiement' => 'array',
        'moyens_transport' => 'array',
        'jours_disponibles' => 'array',
        'gallery' => 'array',
        'imgs' => 'array'
    ];
    
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
