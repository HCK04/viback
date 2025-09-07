<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CentreRadiologieProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_centre',
        'adresse',
        'ville',
        'horaires',
        'horaire_start',
        'horaire_end',
        'gerant_name',
        'services',
        'disponible',
        'absence_start_date',
        'absence_end_date',
        'presentation',
        'description',
        'org_presentation',
        'services_description',
        'additional_info',
        // Media and ratings
        'profile_image',
        'etablissement_image',
        'rating',
        'gallery',
        'imgs',
        // Availability/vacation
        'vacation_mode',
        'vacation_auto_reactivate_date',
        // NEW PROFILE FIELDS
        'moyens_paiement',
        'moyens_transport',
        'informations_pratiques',
        'jours_disponibles',
        'contact_urgence'
    ];
    
    // Add this to handle JSON data
    protected $casts = [
        'services' => 'array',
        'horaires' => 'array',
        'moyens_paiement' => 'array',
        'moyens_transport' => 'array',
        'jours_disponibles' => 'array',
        'gallery' => 'array',
        'rating' => 'float',
        'imgs' => 'array'
    ];
    
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
