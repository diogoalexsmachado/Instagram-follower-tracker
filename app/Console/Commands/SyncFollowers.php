<?php

namespace App\Console\Commands;

use App\Services\FollowerSync;
use App\Services\TrackerConfig;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('followers:sync {username? : Sincroniza só este perfil (opcional)}')]
#[Description('Sincroniza followers para cada perfil listado no config.json e regista follows/unfollows.')]
class SyncFollowers extends Command
{
    public function handle(TrackerConfig $config, FollowerSync $sync): int
    {
        $only = $this->argument('username');
        $profiles = $only ? [ltrim($only, '@')] : $config->profiles();

        $hadError = false;

        foreach ($profiles as $username) {
            $this->info("→ {$username}");
            try {
                $run = $sync->syncByUsername($username);
                $this->line(sprintf(
                    '  OK — fetched=%d, +%d follow, -%d unfollow (total: %d)',
                    $run->followers_fetched,
                    $run->added_count,
                    $run->removed_count,
                    $run->followers_count_after ?? 0
                ));
            } catch (Throwable $e) {
                $hadError = true;
                $this->error("  FAIL: " . $e->getMessage());
            }
        }

        return $hadError ? self::FAILURE : self::SUCCESS;
    }
}
