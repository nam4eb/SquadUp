<?php

namespace App\Filament\Widgets;

use App\Models\Activity;
use App\Models\Clan;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformStatsOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Platform overview';

    protected function getStats(): array
    {
        return [
            Stat::make('Total users', User::query()->count())
                ->description(User::query()->where('created_at', '>=', now()->subDays(7))->count().' new this week'),
            Stat::make('Upcoming activities', Activity::query()
                ->where('starts_at', '>=', now())->whereIn('status', ['open', 'full', 'locked'])->count()),
            Stat::make('Active clans', Clan::query()->where('status', 'active')->count()),
            Stat::make('Messages', Message::query()->count()),
            Stat::make('Open reports', Report::query()->whereIn('status', ['reported', 'under_review'])->count())
                ->color('danger'),
        ];
    }
}
