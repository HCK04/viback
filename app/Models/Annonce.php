<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Annonce extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'title',
        'description',
        'content',
        'type',
        'price',
        'address',
        'phone',
        'email',
        'images',
        'is_active',
        'pourcentage_reduction',
        'category',
        'duration',
        'location',
        'availability',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'float',
        'images' => 'array',
        'availability' => 'array',
        'is_active' => 'boolean',
        'pourcentage_reduction' => 'integer',
        'duration' => 'integer',
        'views_count' => 'integer',
        'rdv_count' => 'integer',
        'published_at' => 'datetime',
    ];

    /**
     * Get the user that owns the annonce.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the discounted price.
     *
     * @return float
     */
    public function getDiscountedPriceAttribute()
    {
        if ($this->pourcentage_reduction > 0 && $this->price > 0) {
            return round($this->price * (1 - $this->pourcentage_reduction / 100), 2);
        }
        
        return $this->price;
    }
    
    /**
     * Get the RDVs associated with this annonce.
     */
    public function rdvs()
    {
        return $this->hasMany(Rdv::class, 'annonce_id');
    }
    
    /**
     * Get the count of RDVs for this annonce.
     *
     * @return int
     */
    public function getRdvCountAttribute()
    {
        return $this->rdvs()->count();
    }
    
    /**
     * Get the count of confirmed RDVs for this annonce.
     *
     * @return int
     */
    public function getConfirmedRdvCountAttribute()
    {
        return $this->rdvs()->where('status', 'confirmed')->count();
    }
    
    /**
     * Scope a query to only include active annonces.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
