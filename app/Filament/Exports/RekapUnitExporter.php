<?php

namespace App\Filament\Exports;

use App\Filament\Widgets\PeriodeStatistik;
use App\Models\Unit;
use App\Support\Analitik\StatistikAgregat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/** Rekap unit bulanan (tautan aktif, baru, klik). Cakupan: admin & pemantau → semua unit; pengelola → unitnya. */
class RekapUnitExporter extends Exporter
{
    protected static ?string $model = Unit::class;

    /** @var array<string, array<string, array{unit: string, aktif: int, baru: int, klik: int}>> */
    private static array $memo = [];

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('kode')->label('Kode'),
            ExportColumn::make('nama')->label('Unit'),
            ExportColumn::make('jenis')->label('Jenis')->state(fn (Unit $r): string => $r->jenis->getLabel()),
            ExportColumn::make('aktif')->label('Tautan aktif')->state(fn (Unit $r, Exporter $e): int => self::baris($r, $e)['aktif'] ?? 0),
            ExportColumn::make('baru')->label('Tautan baru')->state(fn (Unit $r, Exporter $e): int => self::baris($r, $e)['baru'] ?? 0),
            ExportColumn::make('klik')->label('Klik')->state(fn (Unit $r, Exporter $e): int => self::baris($r, $e)['klik'] ?? 0),
        ];
    }

    public static function getOptionsFormComponents(): array
    {
        $bulan = collect(range(0, 11))->mapWithKeys(fn (int $i) => [now()->subMonthsNoOverflow($i)->format('Y-m') => now()->subMonthsNoOverflow($i)->translatedFormat('F Y')])->all();

        return [Select::make('bulan')->label('Bulan')->options($bulan)->placeholder('30 hari terakhir')];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $pengguna = auth()->user();

        if ($pengguna === null) {
            return $query->whereRaw('0 = 1');
        }

        $tercakup = (new StatistikAgregat($pengguna))->unitTercakup();

        return $tercakup === null ? $query : $query->whereIn('id', $tercakup);
    }

    /** @return array{unit: string, aktif: int, baru: int, klik: int} */
    private static function baris(Unit $unit, Exporter $exporter): array
    {
        $pengguna = auth()->user();
        $bulan = $exporter->getOptions()['bulan'] ?? null;
        $kunci = ($pengguna?->getKey() ?? '-').'|'.($bulan ?? '30h');

        if (! isset(self::$memo[$kunci])) {
            [$dari, $sampai] = PeriodeStatistik::dari($bulan);
            self::$memo = [$kunci => (new StatistikAgregat($pengguna))->rekapUnit($dari, $sampai)->keyBy('unit')->all()];
        }

        return self::$memo[$kunci][$unit->nama] ?? ['unit' => $unit->nama, 'aktif' => 0, 'baru' => 0, 'klik' => 0];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Ekspor rekap unit selesai: '.Number::format($export->successful_rows).' baris. Berkas dihapus otomatis setelah 24 jam.';
    }

    public function getFileDisk(): string
    {
        return 'tmp';
    }
}
