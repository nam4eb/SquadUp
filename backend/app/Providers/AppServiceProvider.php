<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\ActivityParticipant;
use App\Models\Clan;
use App\Models\ClanEvent;
use App\Policies\ActivityPolicy;
use App\Policies\ClanPolicy;
use App\Support\ClanMetricsCache;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Activity::class, ActivityPolicy::class);
        Gate::policy(Clan::class, ClanPolicy::class);
        ClanEvent::created(fn (ClanEvent $event) => ClanMetricsCache::invalidate($event->clan_id));
        ClanEvent::deleted(fn (ClanEvent $event) => ClanMetricsCache::invalidate($event->clan_id));
        ActivityParticipant::saved(fn (ActivityParticipant $participant) => ClanMetricsCache::invalidateActivity($participant->activity));
        ActivityParticipant::deleted(fn (ActivityParticipant $participant) => ClanMetricsCache::invalidateActivity($participant->activity));
        Activity::updated(function (Activity $activity): void {
            if ($activity->wasChanged(['host_id', 'topic_id', 'status', 'starts_at'])) {
                ClanMetricsCache::invalidateActivity($activity);
            }
        });
        RateLimiter::for('auth', function (Request $request): array {
            return [
                Limit::perMinute(10)->by($request->ip()),
                Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email'))),
            ];
        });
        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(config('performance.api_rate_limit_per_minute', 120))->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
        RateLimiter::for('report', function (Request $request): Limit {
            return Limit::perHour(10)->by(
                $request->user()?->getAuthIdentifier() ?? $request->ip()
            );
        });
    }
}
