<?php

namespace App\Filament\Widgets;

use App\Models\CrashRound;
use Filament\Widgets\ChartWidget;

class CrashRoundsTrendWidget extends ChartWidget
{
    protected ?string $heading = 'Crash rounds per day';

    protected ?string $description = 'Last 7 calendar days';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $labels = [];
        $values = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->startOfDay();
            $labels[] = $day->format('M j');
            $values[] = CrashRound::query()
                ->whereDate('created_at', $day->toDateString())
                ->count();
        }

        return [
            'datasets' => [
                [
                    'label' => __('Rounds'),
                    'data' => $values,
                    'borderColor' => '#22c55e',
                    'backgroundColor' => 'rgba(34,197,94,0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
