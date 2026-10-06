<?php

namespace App\Filament\Exports;

use App\Models\TautanPendek;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

/** Rekap tautan sesuai cakupan peran (terlihatOleh). Tidak memuat kata sandi, ip_hash, atau data kunjungan rinci. */
class RekapTautanExporter extends Exporter
{
    protected static ?string $model = TautanPendek::class;

    /** @return array<ExportColumn> */
    public static function getColumns(): array
    {
        return [
            ExportColumn::make('kode')->label('Kode'),
            ExportColumn::make('judul')->label('Judul'),
            ExportColumn::make('url_tujuan')->label('URL tujuan'),
            ExportColumn::make('status')->label('Status')->state(fn (TautanPendek $r): string => $r->status->getLabel()),
            ExportColumn::make('pemilik')->label('Pemilik')
                ->state(fn (TautanPendek $r): string => $r->unit_id !== null ? 'Unit: '.$r->unit?->nama : (string) $r->pemilik?->name),
            ExportColumn::make('jumlah_klik')->label('Klik'),
            ExportColumn::make('status_cek_tujuan')->label('Cek tujuan')->state(fn (TautanPendek $r): string => $r->status_cek_tujuan->getLabel()),
            ExportColumn::make('created_at')->label('Dibuat'),
        ];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        $pengguna = auth()->user();

        if ($pengguna === null) {
            return $query->whereRaw('0 = 1');
        }

        // Cakupan identik BR-20 (terlihatOleh), dipasang sebagai subquery agar tidak bergantung pada tipe builder.
        return $query
            ->whereIn('id', TautanPendek::query()->terlihatOleh($pengguna)->select('id')->toBase())
            ->with(['pemilik', 'unit']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return 'Ekspor rekap tautan selesai: '.Number::format($export->successful_rows).' baris. Berkas dihapus otomatis setelah 24 jam.';
    }

    public function getFileDisk(): string
    {
        return 'tmp';
    }
}
