<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rdv extends Model
{
    use HasFactory;

    protected $table = 'rdv';
    
    // Enable auto-incrementing for integer IDs
    public $incrementing = true;
    
    // Set the key type to integer
    protected $keyType = 'int';

    protected $fillable = [
        'patient_id',
        'patient_name',
        'patient_phone',
        'patient_email',
        'target_user_id',
        'target_role',
        'annonce_id',
        'date_time',
        'status',
        'reason',
        'notes',
    ];

    protected $casts = [
        'date_time' => 'datetime',
    ];

    public function patient()
    {
        return $this->belongsTo(User::class, 'patient_id');
    }

    public function target()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }

    public function annonce()
    {
        return $this->belongsTo(Annonce::class, 'annonce_id');
    }
}
