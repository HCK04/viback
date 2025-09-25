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
        // Santé fields
        'sante_documents',
        'sante_antecedents_medicaux',
        'sante_traitements_reguliers',
        'sante_allergies',
        'sante_antecedents_familiaux',
        'sante_operations_chirurgicales',
        'sante_vaccins',
        'sante_mesures',
        'sante_documents_none',
        'sante_antecedents_medicaux_none',
        'sante_traitements_reguliers_none',
        'sante_allergies_none',
        'sante_antecedents_familiaux_none',
        'sante_operations_chirurgicales_none',
        'sante_vaccins_none',
        'sante_mesures_none',
    ];

    protected $casts = [
        'sante_documents' => 'array',
        'sante_antecedents_medicaux' => 'array',
        'sante_traitements_reguliers' => 'array',
        'sante_allergies' => 'array',
        'sante_antecedents_familiaux' => 'array',
        'sante_operations_chirurgicales' => 'array',
        'sante_vaccins' => 'array',
        'sante_mesures' => 'array',
        'sante_documents_none' => 'boolean',
        'sante_antecedents_medicaux_none' => 'boolean',
        'sante_traitements_reguliers_none' => 'boolean',
        'sante_allergies_none' => 'boolean',
        'sante_antecedents_familiaux_none' => 'boolean',
        'sante_operations_chirurgicales_none' => 'boolean',
        'sante_vaccins_none' => 'boolean',
        'sante_mesures_none' => 'boolean',
    ];
}
