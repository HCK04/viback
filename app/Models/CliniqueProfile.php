<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CliniqueProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nom_clinique',
        'adresse',
        'ville',
        'localisation',
        'horaires',
        'horaire_start',
        'horaire_end',
        'nbr_personnel',
        'gerant_name',
        'services',
        'disponible',
        'absence_start_date',
        'absence_end_date',
        'presentation',
        'additional_info',
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
        'jours_disponibles' => 'array'
    ];
    
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
