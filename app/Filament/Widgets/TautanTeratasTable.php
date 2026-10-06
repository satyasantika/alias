<?php

namespace App\Filament\Widgets;

use App\Enums\Izin;
use App\Support\Analitik\StatistikAgregat;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

/** 10 tautan teratas pada periode. Hanya kode, judul, unit, klik: tidak ada tautan ke detail atau tujuan. */
class TautanTeratasTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected static ?string $heading = '10 tautan teratas';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $pengguna = auth()->user();

        return $pengguna !== null && ($pengguna->can(Izin::AnalitikLihat->value) || $pengguna->can(Izin::AnalitikLihatAgregat->value));
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(function (): array {
                [$dari, $sampai] = PeriodeStatistik::dari($this->pageFilters['bulan'] ?? null);

                return (new StatistikAgregat(auth()->user()))->tautanTeratas($dari, $sampai)
                    ->mapWithKeys(fn (array $b) => [$b['kode'] => $b])->all();
            })
            ->columns([
                TextColumn::make('kode')->label('Kode'),
                TextColumn::make('judul')->label('Judul')->limit(50),
                TextColumn::make('unit')->label('Unit'),
                TextColumn::make('klik')->label('Klik')->numeric(),
            ])
            ->paginated(false);
    }
}
