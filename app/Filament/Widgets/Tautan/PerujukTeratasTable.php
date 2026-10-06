<?php

namespace App\Filament\Widgets\Tautan;

use App\Enums\DimensiRekap;
use App\Models\TautanPendek;
use App\Support\Analitik\StatistikTautan;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Model;

/** Perujuk teratas (30 hari) dari rekap dimensi + data mentah hari yang belum direkap. */
class PerujukTeratasTable extends TableWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Perujuk teratas (30 hari)';

    protected int|string|array $columnSpan = 'full';

    /** Hanya dipasang di halaman lihat tautan, bukan di dasbor. */
    protected static bool $isDiscovered = false;

    public ?Model $record = null;

    public function table(Table $table): Table
    {
        $tautan = $this->record instanceof TautanPendek ? $this->record : null;
        $data = $tautan === null ? collect() : (new StatistikTautan($tautan))->dimensi(DimensiRekap::PerujukHost, 30);

        // Baris tiruan dari hasil agregasi agar dapat ditampilkan tabel Filament tanpa model.
        return $table
            ->records(fn (): array => $data->map(fn (int $jumlah, string $nilai) => ['nilai' => $nilai, 'jumlah' => $jumlah])->all())
            ->columns([
                TextColumn::make('nilai')->label('Perujuk'),
                TextColumn::make('jumlah')->label('Klik')->numeric(),
            ])
            ->paginated(false);
    }
}
