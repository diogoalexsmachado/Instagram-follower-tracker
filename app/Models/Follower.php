<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Follower extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'bool',
        'is_private' => 'bool',
        'is_active' => 'bool',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'unfollowed_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }
}
