<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'age',
        'gender',
        'blood_type',
        'allergies',
        'chronic_diseases',
        'missed_rdv',
    ];
}
