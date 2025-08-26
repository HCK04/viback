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
        'localisation',
        'horaires',
        'nbr_personnel',
        'gerant_name',
        'services',
        'disponible',
        'absence_start_date',
        'absence_end_date',
    ];

    // Add this to handle JSON data
    protected $casts = [
        'services' => 'array',
        'horaires' => 'array',
    ];
}
