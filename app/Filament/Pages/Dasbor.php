<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AntreanModerasiStats;
use App\Filament\Widgets\KlikPerUnitChart;
use App\Filament\Widgets\RingkasanTautanSaya;
use App\Filament\Widgets\TautanTeratasTable;
use Filament\Pages\Dashboard;

/** Dasbor tunggal; widget tampil sesuai permission/peran masing-masing (canView). */
class Dasbor extends Dashboard
{
    protected static ?string $title = 'Dasbor';

    /** @return array<class-string> */
    public function getWidgets(): array
    {
        return [RingkasanTautanSaya::class, AntreanModerasiStats::class, KlikPerUnitChart::class, TautanTeratasTable::class];
    }
}
