<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParapharmacieProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_parapharmacie',
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
        'description',
        'org_presentation',
        'services_description',
        'additional_info',
        'services',
        'guard',
        // NEW PROFILE FIELDS
        'moyens_paiement',
        'moyens_transport',
        'informations_pratiques',
        'jours_disponibles',
        'contact_urgence',
        'imgs'
    ];
    
    // Add this to handle JSON data
    protected $casts = [
        'horaires' => 'array',
        'services' => 'array',
        'moyens_paiement' => 'array',
        'moyens_transport' => 'array',
        'jours_disponibles' => 'array',
        'imgs' => 'array'
    ];
    
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
