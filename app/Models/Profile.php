<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Profile extends Model
{
    protected $guarded = [];

    protected $casts = [
        'is_verified' => 'bool',
        'is_private' => 'bool',
        'followers_count' => 'int',
        'following_count' => 'int',
        'media_count' => 'int',
        'last_synced_at' => 'datetime',
    ];

    public function followers(): HasMany
    {
        return $this->hasMany(Follower::class);
    }

    public function activeFollowers(): HasMany
    {
        return $this->hasMany(Follower::class)->where('is_active', true);
    }

    public function events(): HasMany
    {
        return $this->hasMany(FollowEvent::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }
}
