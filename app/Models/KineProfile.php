<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KineProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialty',
        'experience_years',
        'horaires',
        'diplomas',
        'adresse',
        'disponible',
        'absence_start_date',
        'absence_end_date',
        'presentation',
        'carte_professionnelle',
        'experiences'
    ];

    protected $casts = [
        'diplomas' => 'array',
        'diplomes' => 'array',
        'experiences' => 'array'
    ];
    
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
