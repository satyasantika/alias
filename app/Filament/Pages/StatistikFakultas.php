<?php

namespace App\Filament\Pages;

use App\Enums\Izin;
use App\Filament\Exports\RekapUnitExporter;
use App\Filament\Widgets\KlikPerUnitChart;
use App\Filament\Widgets\RekapUnitTable;
use App\Filament\Widgets\TautanTeratasTable;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/** Statistik agregat (analitik.lihat-agregat): per unit, 10 tautan teratas, filter bulan. */
class StatistikFakultas extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'statistik-fakultas';

    protected static ?string $title = 'Statistik fakultas';

    protected static ?string $navigationLabel = 'Statistik fakultas';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Statistik';

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Izin::AnalitikLihatAgregat->value) ?? false;
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()->exporter(RekapUnitExporter::class)->label('Ekspor rekap unit')
                ->visible(fn (): bool => auth()->user()?->can(Izin::AnalitikEkspor->value) ?? false),
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        $bulan = collect(range(0, 11))->mapWithKeys(fn (int $i) => [now()->subMonthsNoOverflow($i)->format('Y-m') => now()->subMonthsNoOverflow($i)->translatedFormat('F Y')])->all();

        return $schema->components([
            Select::make('bulan')->label('Bulan')->options($bulan)->placeholder('30 hari terakhir'),
        ]);
    }

    /** @return array<class-string> */
    public function getWidgets(): array
    {
        return [RekapUnitTable::class, KlikPerUnitChart::class, TautanTeratasTable::class];
    }
}
