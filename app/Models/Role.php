<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = ['name', 'display_name', 'category_id'];

    /**
     * Get the users associated with the role.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
