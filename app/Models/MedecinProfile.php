<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedecinProfile extends Model
{
    use HasFactory;

    protected $table = 'medecin_profiles';

    protected $fillable = [
        'user_id',
        'specialty',
        'experience_years',
        'horaires',
        'horaire_start',
        'horaire_end',
        'diplomas',
        'diplomes',
        'adresse',
        'ville',
        'profile_image',
        'disponible',
        'vacation_mode',
        'absence_start_date',
        'absence_end_date',
        'vacation_auto_reactivate_date',
        'gallery',
        'presentation',
        'additional_info',
        'carte_professionnelle',
        'experiences',
        'rating',
        // NEW PROFILE FIELDS
        'numero_carte_professionnelle',
        'moyens_paiement',
        'moyens_transport',
        'informations_pratiques',
        'jours_disponibles',
        'contact_urgence',
        'rdv_patients_suivis_uniquement',
        'imgs'
    ];

    protected $casts = [
        'diplomas' => 'array',
        'diplomes' => 'array',
        'experiences' => 'array',
        'moyens_paiement' => 'array',
        'moyens_transport' => 'array',
        'jours_disponibles' => 'array',
        'imgs' => 'array'
    ];
    
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
