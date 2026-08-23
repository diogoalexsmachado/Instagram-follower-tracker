<?php

namespace App\Http\Controllers;

use App\Models\FollowEvent;
use App\Models\Profile;
use App\Models\SyncRun;
use App\Services\TrackerConfig;
use Illuminate\Http\Request;

class ProfileController
{
    public function index(TrackerConfig $config)
    {
        $tracked = $config->profiles();

        $profiles = Profile::whereIn('username', $tracked)
            ->orderBy('username')
            ->get()
            ->keyBy('username');

        $lastRuns = SyncRun::query()
            ->whereIn('profile_id', $profiles->pluck('id'))
            ->where('status', SyncRun::STATUS_SUCCESS)
            ->latest('started_at')
            ->get()
            ->groupBy('profile_id')
            ->map(fn ($runs) => $runs->first());

        $rows = collect($tracked)->map(function (string $username) use ($profiles, $lastRuns) {
            $profile = $profiles->get($username);

            return [
                'username' => $username,
                'profile' => $profile,
                'last_run' => $profile ? $lastRuns->get($profile->id) : null,
            ];
        });

        return view('profiles.index', ['rows' => $rows]);
    }

    public function show(string $username)
    {
        $profile = Profile::where('username', $username)->firstOrFail();

        $recentEvents = FollowEvent::where('profile_id', $profile->id)
            ->latest('occurred_at')
            ->limit(100)
            ->get();

        $activeFollowers = $profile->activeFollowers()
            ->orderByDesc('first_seen_at')
            ->limit(50)
            ->get();

        $recentlyLost = $profile->followers()
            ->where('is_active', false)
            ->orderByDesc('unfollowed_at')
            ->limit(50)
            ->get();

        $countHistory = SyncRun::where('profile_id', $profile->id)
            ->where('status', SyncRun::STATUS_SUCCESS)
            ->orderBy('finished_at')
            ->limit(500)
            ->get(['finished_at', 'followers_count_after', 'added_count', 'removed_count'])
            ->map(fn ($r) => [
                'at' => $r->finished_at?->toIso8601String(),
                'count' => $r->followers_count_after,
                'added' => $r->added_count,
                'removed' => $r->removed_count,
            ]);

        $totals = [
            'follows_24h' => FollowEvent::where('profile_id', $profile->id)
                ->where('event_type', FollowEvent::TYPE_FOLLOW)
                ->where('occurred_at', '>=', now()->subDay())
                ->count(),
            'unfollows_24h' => FollowEvent::where('profile_id', $profile->id)
                ->where('event_type', FollowEvent::TYPE_UNFOLLOW)
                ->where('occurred_at', '>=', now()->subDay())
                ->count(),
            'follows_7d' => FollowEvent::where('profile_id', $profile->id)
                ->where('event_type', FollowEvent::TYPE_FOLLOW)
                ->where('occurred_at', '>=', now()->subDays(7))
                ->count(),
            'unfollows_7d' => FollowEvent::where('profile_id', $profile->id)
                ->where('event_type', FollowEvent::TYPE_UNFOLLOW)
                ->where('occurred_at', '>=', now()->subDays(7))
                ->count(),
        ];

        return view('profiles.show', compact(
            'profile',
            'recentEvents',
            'activeFollowers',
            'recentlyLost',
            'countHistory',
            'totals'
        ));
    }
}
