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
        'diplomas',
        'adresse',
        'profile_image',
        'disponible',
        'absence_start_date',
        'absence_end_date',
        'gallery'
    ];
    
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
