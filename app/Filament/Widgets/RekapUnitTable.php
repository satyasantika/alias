<?php

namespace App\Filament\Widgets;

use App\Enums\Izin;
use App\Support\Analitik\StatistikAgregat;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** Rekap unit per bulan: tautan aktif, tautan baru, total klik (PRD §10). */
class RekapUnitTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Rekap per unit';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can(Izin::AnalitikLihatAgregat->value) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                [$dari, $sampai] = PeriodeStatistik::dari($this->pageFilters['bulan'] ?? null);

                return (new StatistikAgregat(auth()->user()))->rekapUnit($dari, $sampai)->mapWithKeys(fn (array $b) => [$b['unit'] => $b])->all();
            })
            ->columns([
                TextColumn::make('unit')->label('Unit'),
                TextColumn::make('aktif')->label('Tautan aktif')->numeric(),
                TextColumn::make('baru')->label('Tautan baru')->numeric(),
                TextColumn::make('klik')->label('Klik')->numeric(),
            ])
            ->paginated(false);
    }
}
