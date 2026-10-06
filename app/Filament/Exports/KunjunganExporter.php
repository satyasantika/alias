<?php

namespace App\Filament\Exports;

use App\Enums\Izin;
use App\Models\KunjunganTautan;
use App\Models\TautanPendek;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/** Kunjungan anonim. Tanpa ip_hash dan tanpa user agent. Hanya tautan yang terlihat dan hanya pemegang kunjungan.lihat-rinci. */
class KunjunganExporter extends Exporter
{
    protected static ?string $model = KunjunganTautan::class;

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('tautan.kode')->label('Kode tautan'),
            ExportColumn::make('dikunjungi_pada')->label('Waktu'),
            ExportColumn::make('ip_anonim')->label('IP (dianonimkan)'),
            ExportColumn::make('peramban')->label('Peramban'),
            ExportColumn::make('versi_peramban')->label('Versi peramban'),
            ExportColumn::make('os')->label('OS'),
            ExportColumn::make('versi_os')->label('Versi OS'),
            ExportColumn::make('jenis_perangkat')->label('Perangkat')->state(fn (KunjunganTautan $r): string => $r->jenis_perangkat->getLabel()),
            ExportColumn::make('perujuk_host')->label('Perujuk'),
            ExportColumn::make('bot')->label('Bot')->state(fn (KunjunganTautan $r): string => $r->bot ? 'ya' : 'tidak'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $pengguna = auth()->user();

        if ($pengguna === null || ! $pengguna->can(Izin::KunjunganLihatRinci->value)) {
            return $query->whereRaw('0 = 1');
        }

        return $query
            ->whereIn('tautan_pendek_id', TautanPendek::query()->terlihatOleh($pengguna)->select('id')->toBase())
            ->with('tautan');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Ekspor kunjungan selesai: '.Number::format($export->successful_rows).' baris. Berkas dihapus otomatis setelah 24 jam.';
    }

    public function getFileDisk(): string
    {
        return 'tmp';
    }
}
