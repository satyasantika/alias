<?php

namespace App\Filament\Exports;

use App\Enums\Izin;
use App\Models\LaporanPenyalahgunaan;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/** Rekap moderasi (XLSX) untuk admin: kategori, status, tindakan, waktu tindak lanjut. Tanpa ip_hash dan surel pelapor. */
class LaporanModerasiExporter extends Exporter
{
    protected static ?string $model = LaporanPenyalahgunaan::class;

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('created_at')->label('Masuk'),
            ExportColumn::make('kode_dilaporkan')->label('Kode'),
            ExportColumn::make('kategori')->label('Kategori')->state(fn (LaporanPenyalahgunaan $r): string => $r->kategori->getLabel()),
            ExportColumn::make('status')->label('Status')->state(fn (LaporanPenyalahgunaan $r): string => $r->status->getLabel()),
            ExportColumn::make('tindakan')->label('Tindakan'),
            ExportColumn::make('ditangani_pada')->label('Ditangani'),
            ExportColumn::make('jam_tindak_lanjut')->label('Jam hingga ditangani')
                ->state(fn (LaporanPenyalahgunaan $r): ?float => $r->ditangani_pada === null ? null : round($r->created_at->diffInMinutes($r->ditangani_pada, true) / 60, 1)),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return (auth()->user()?->can(Izin::ModerasiKelola->value) ?? false) ? $query : $query->whereRaw('0 = 1');
    }

    /** @return array<\Filament\Actions\Exports\Enums\Contracts\ExportFormat> */
    public function getFormats(): array
    {
        return [ExportFormat::Xlsx];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Ekspor rekap moderasi selesai: '.Number::format($export->successful_rows).' baris. Berkas dihapus otomatis setelah 24 jam.';
    }

    public function getFileDisk(): string
    {
        return 'tmp';
    }
}
