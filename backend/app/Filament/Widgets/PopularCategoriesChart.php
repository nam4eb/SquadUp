<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PopularCategoriesChart extends ChartWidget
{
    protected ?string $heading = 'Popular activity categories';

    protected function getData(): array
    {
        $rows = DB::table('activities')
            ->join('activity_categories', 'activity_categories.id', '=', 'activities.category_id')
            ->whereNull('activities.deleted_at')
            ->groupBy('activity_categories.id', 'activity_categories.name')
            ->select('activity_categories.name', DB::raw('COUNT(*) as total'))
            ->orderByDesc('total')->limit(10)->get();

        return [
            'datasets' => [[
                'label' => 'Activities',
                'data' => $rows->pluck('total')->map(fn ($value) => (int) $value)->all(),
                'backgroundColor' => '#f59e0b',
            ]],
            'labels' => $rows->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
