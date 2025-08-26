<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SiteStat extends Model
{
    use HasFactory;

    protected $table = 'site_stats';
    protected $fillable = ['baseline', 'start_time'];
    public $timestamps = false;
}
