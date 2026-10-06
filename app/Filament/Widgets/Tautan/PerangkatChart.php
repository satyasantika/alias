<?php

namespace App\Filament\Widgets\Tautan;

use App\Enums\DimensiRekap;
use App\Models\TautanPendek;
use App\Support\Analitik\StatistikTautan;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class PerangkatChart extends ChartWidget
{
    protected ?string $heading = 'Perangkat (30 hari)';

    protected static ?int $sort = 2;

    /** Hanya dipasang di halaman lihat tautan, bukan di dasbor. */
    protected static bool $isDiscovered = false;

    public ?Model $record = null;

    protected function getType(): string
    {
        return 'doughnut';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $data = $this->record instanceof TautanPendek
            ? (new StatistikTautan($this->record))->dimensi(DimensiRekap::JenisPerangkat, 30)
            : collect();

        return [
            'datasets' => [['label' => 'Klik', 'data' => $data->values()->all()]],
            'labels' => $data->keys()->all(),
        ];
    }
}
