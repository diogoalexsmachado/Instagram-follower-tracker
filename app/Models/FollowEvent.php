<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FollowEvent extends Model
{
    public const TYPE_FOLLOW = 'follow';
    public const TYPE_UNFOLLOW = 'unfollow';

    protected $guarded = [];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(SyncRun::class);
    }
}
