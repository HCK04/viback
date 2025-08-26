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
        'horaires',
        'gerant_name',
        'disponible',
        'absence_start_date',
        'absence_end_date',
    ];
    
    // Add this to handle JSON data
    protected $casts = [
        'horaires' => 'array',
    ];
}
