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
        'horaires',
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
