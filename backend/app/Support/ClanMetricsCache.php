<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ClanLeaderboardSnapshot;
use Illuminate\Support\Facades\Cache;

final class ClanMetricsCache
{
    public static function version(string $clanId): int
    {
        return (int) Cache::rememberForever("clan:{$clanId}:metrics-version", fn (): int => 1);
    }

    public static function invalidate(string $clanId): void
    {
        $key = "clan:{$clanId}:metrics-version";
        if (! Cache::has($key)) {
            Cache::forever($key, 1);
        }
        Cache::increment($key);
        Cache::forget("clan:{$clanId}:statistics");
        ClanLeaderboardSnapshot::query()->where('clan_id', $clanId)->delete();
    }

    public static function invalidateActivity(Activity $activity): void
    {
        $clanId = $activity->clanEvent()->value('clan_id');
        if ($clanId) {
            self::invalidate($clanId);
        }
    }
}
