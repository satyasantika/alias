<?php

namespace App\Filament\Widgets\Tautan;

use App\Models\TautanPendek;
use App\Support\Analitik\StatistikTautan;
use Filament\Widgets\ChartWidget;
use Illuminate\Database\Eloquent\Model;

class KlikHarianChart extends ChartWidget
{
    protected ?string $heading = 'Klik harian';

    protected ?string $description = 'Pengunjung unik dihitung per hari (hash IP berganti tiap hari), sehingga total unik adalah jumlah harian. Bot tidak dihitung.';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    /** Hanya dipasang di halaman lihat tautan, bukan di dasbor. */
    protected static bool $isDiscovered = false;

    public ?Model $record = null;

    public ?string $filter = '30';

    /** @return array<int|string, string> */
    protected function getFilters(): array
    {
        return ['30' => '30 hari', '90' => '90 hari'];
    }

    protected function getType(): string
    {
        return 'line';
    }

    /** @return array<string, mixed> */
    protected function getData(): array
    {
        $harian = $this->record instanceof TautanPendek
            ? (new StatistikTautan($this->record))->klikHarian((int) ($this->filter ?? 30))
            : collect();

        return [
            'datasets' => [
                ['label' => 'Klik', 'data' => $harian->pluck('klik')->all()],
                ['label' => 'Pengunjung unik (per hari)', 'data' => $harian->pluck('unik')->all(), 'borderDash' => [5, 5]],
            ],
            'labels' => $harian->keys()->map(fn (string $t) => date('d M', strtotime($t)))->all(),
        ];
    }
}
