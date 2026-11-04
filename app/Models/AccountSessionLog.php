<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $session_hash
 * @property string $device_name
 * @property string|null $latitude
 * @property string|null $longitude
 * @property Carbon $logged_in_at
 * @property Carbon $last_activity_at
 * @property Carbon|null $logged_out_at
 */
#[Fillable([
    'user_id',
    'session_hash',
    'device_name',
    'latitude',
    'longitude',
    'logged_in_at',
    'last_activity_at',
    'logged_out_at',
])]
class AccountSessionLog extends Model
{
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'logged_in_at' => 'immutable_datetime',
            'last_activity_at' => 'immutable_datetime',
            'logged_out_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
