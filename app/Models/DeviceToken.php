<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeviceToken extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'expo_push_token',
        'platform',
        'app_version',
        'last_used_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the device token.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Validate if the token format is valid Expo push token.
     *
     * @param string $token
     * @return bool
     */
    public static function isValidExpoPushToken($token)
    {
        // Expo push tokens follow the format: ExponentPushToken[...] or ExpoPushToken[...]
        return preg_match('/^Expo(nent)?PushToken\[[\w-]+\]$/', $token) === 1;
    }
}
