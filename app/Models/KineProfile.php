<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KineProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'specialty',  // Add this line
        'experience_years',
        'horaires',
        'start_time',
        'end_time',
        'diplomas',
        'adresse',
        'disponible',
        'absence_start_date',
        'absence_end_date',
    ];
    
    public function user() {
        return $this->belongsTo(User::class, 'user_id');
    }
}
