<?php

namespace App\Services;

use App\Models\FollowEvent;
use App\Models\Follower;
use App\Models\Profile;
use App\Models\SyncRun;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FollowerSync
{
    public function __construct(private InstagramClient $client)
    {
    }

    public function syncByUsername(string $username): SyncRun
    {
        $meta = $this->client->fetchProfile($username);

        $profile = Profile::updateOrCreate(
            ['username' => $meta['username']],
            [
                'ig_user_id' => $meta['ig_user_id'],
                'full_name' => $meta['full_name'],
                'profile_pic_url' => $meta['profile_pic_url'],
                'biography' => $meta['biography'],
                'is_verified' => $meta['is_verified'],
                'is_private' => $meta['is_private'],
            ]
        );

        $followersCountBefore = $profile->followers_count;

        $run = SyncRun::create([
            'profile_id' => $profile->id,
            'started_at' => now(),
            'status' => SyncRun::STATUS_RUNNING,
            'followers_count_before' => $followersCountBefore,
        ]);

        try {
            $seenIds = [];
            $fetched = 0;
            $now = now();

            foreach ($this->client->iterateFollowers($profile->ig_user_id) as $u) {
                if ($u['ig_user_id'] === '' || $u['username'] === '') {
                    continue;
                }

                $seenIds[] = $u['ig_user_id'];
                $fetched++;

                $existing = Follower::where('profile_id', $profile->id)
                    ->where('ig_user_id', $u['ig_user_id'])
                    ->first();

                if ($existing === null) {
                    // Novo follower — só regista evento se o perfil já tem histórico.
                    // No primeiro sync não emite +N eventos (seria ruído).
                    Follower::create([
                        'profile_id' => $profile->id,
                        'ig_user_id' => $u['ig_user_id'],
                        'username' => $u['username'],
                        'full_name' => $u['full_name'],
                        'profile_pic_url' => $u['profile_pic_url'],
                        'is_verified' => $u['is_verified'],
                        'is_private' => $u['is_private'],
                        'first_seen_at' => $now,
                        'last_seen_at' => $now,
                        'is_active' => true,
                        'unfollowed_at' => null,
                    ]);

                    if ($followersCountBefore !== null) {
                        FollowEvent::create([
                            'profile_id' => $profile->id,
                            'sync_run_id' => $run->id,
                            'ig_user_id' => $u['ig_user_id'],
                            'username' => $u['username'],
                            'full_name' => $u['full_name'],
                            'profile_pic_url' => $u['profile_pic_url'],
                            'event_type' => FollowEvent::TYPE_FOLLOW,
                            'occurred_at' => $now,
                        ]);
                    }
                } else {
                    $updates = [
                        'username' => $u['username'],
                        'full_name' => $u['full_name'],
                        'profile_pic_url' => $u['profile_pic_url'],
                        'is_verified' => $u['is_verified'],
                        'is_private' => $u['is_private'],
                        'last_seen_at' => $now,
                    ];

                    if (! $existing->is_active) {
                        // Refollow: já tinha dado unfollow antes, agora voltou.
                        $updates['is_active'] = true;
                        $updates['unfollowed_at'] = null;

                        FollowEvent::create([
                            'profile_id' => $profile->id,
                            'sync_run_id' => $run->id,
                            'ig_user_id' => $u['ig_user_id'],
                            'username' => $u['username'],
                            'full_name' => $u['full_name'],
                            'profile_pic_url' => $u['profile_pic_url'],
                            'event_type' => FollowEvent::TYPE_FOLLOW,
                            'occurred_at' => $now,
                        ]);
                    }

                    $existing->update($updates);
                }
            }

            // Unfollows: quem estava activo e não apareceu nesta run.
            $addedCount = FollowEvent::where('sync_run_id', $run->id)
                ->where('event_type', FollowEvent::TYPE_FOLLOW)
                ->count();

            $removedCount = $this->markUnfollowers($profile, $seenIds, $run, $now);

            $profile->update([
                'followers_count' => $meta['followers_count'],
                'following_count' => $meta['following_count'],
                'media_count' => $meta['media_count'],
                'full_name' => $meta['full_name'],
                'profile_pic_url' => $meta['profile_pic_url'],
                'biography' => $meta['biography'],
                'is_verified' => $meta['is_verified'],
                'is_private' => $meta['is_private'],
                'last_synced_at' => $now,
                'last_error' => null,
            ]);

            $run->update([
                'finished_at' => now(),
                'status' => SyncRun::STATUS_SUCCESS,
                'followers_fetched' => $fetched,
                'added_count' => $addedCount,
                'removed_count' => $removedCount,
                'followers_count_after' => $meta['followers_count'],
            ]);

            return $run->refresh();
        } catch (Throwable $e) {
            Log::error("FollowerSync failed for {$username}", ['exception' => $e]);

            $profile->update(['last_error' => $e->getMessage()]);

            $run->update([
                'finished_at' => now(),
                'status' => SyncRun::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * @param  string[]  $seenIds
     */
    private function markUnfollowers(Profile $profile, array $seenIds, SyncRun $run, Carbon $now): int
    {
        $removed = 0;

        // Trabalha em batches — usa NOT IN via chunks para evitar problemas com listas gigantes.
        // Para até 10k followers, uma única query serve.
        $query = Follower::where('profile_id', $profile->id)
            ->where('is_active', true);

        if (! empty($seenIds)) {
            $query->whereNotIn('ig_user_id', $seenIds);
        }

        $query->orderBy('id')->chunkById(200, function ($chunk) use ($run, $profile, $now, &$removed) {
            foreach ($chunk as $f) {
                DB::transaction(function () use ($f, $run, $profile, $now) {
                    $f->update([
                        'is_active' => false,
                        'unfollowed_at' => $now,
                    ]);

                    FollowEvent::create([
                        'profile_id' => $profile->id,
                        'sync_run_id' => $run->id,
                        'ig_user_id' => $f->ig_user_id,
                        'username' => $f->username,
                        'full_name' => $f->full_name,
                        'profile_pic_url' => $f->profile_pic_url,
                        'event_type' => FollowEvent::TYPE_UNFOLLOW,
                        'occurred_at' => $now,
                    ]);
                });
                $removed++;
            }
        });

        return $removed;
    }
}
