<?php

namespace App\Filament\Widgets;

use App\Enums\Izin;
use App\Support\Analitik\StatistikAgregat;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class KlikPerUnitChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Klik per unit';

    public static function canView(): bool
    {
        return auth()->user()?->can(Izin::AnalitikLihatAgregat->value) ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        [$dari, $sampai] = PeriodeStatistik::dari($this->pageFilters['bulan'] ?? null);
        $data = (new StatistikAgregat(auth()->user()))->klikPerUnit($dari, $sampai);

        return [
            'datasets' => [['label' => 'Klik', 'data' => $data->values()->all()]],
            'labels' => $data->keys()->all(),
        ];
    }
}
