<?php

namespace App\Filament\Widgets;

use App\Enums\Izin;
use App\Enums\StatusLaporan;
use App\Models\LaporanPenyalahgunaan;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AntreanModerasiStats extends StatsOverviewWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Antrean moderasi';

    public static function canView(): bool
    {
        return auth()->user()?->can(Izin::ModerasiKelola->value) ?? false;
    }

    /** Rata-rata jam dari laporan masuk hingga ditangani (30 hari terakhir). */
    public static function rataRataJamTindakLanjut(): ?float
    {
        $selesai = LaporanPenyalahgunaan::query()
            ->whereNotNull('ditangani_pada')
            ->where('ditangani_pada', '>=', now()->subDays(30))
            ->get(['created_at', 'ditangani_pada']);

        if ($selesai->isEmpty()) {
            return null;
        }

        return round($selesai->avg(fn (LaporanPenyalahgunaan $l) => $l->created_at->diffInMinutes($l->ditangani_pada, true)) / 60, 1);
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $rata = self::rataRataJamTindakLanjut();

        return [
            Stat::make('Laporan baru', LaporanPenyalahgunaan::where('status', StatusLaporan::Baru)->count())->color('danger'),
            Stat::make('Sedang ditinjau', LaporanPenyalahgunaan::where('status', StatusLaporan::Ditinjau)->count())->color('warning'),
            Stat::make('Rata-rata waktu tindak lanjut (30 hari)', $rata === null ? '—' : $rata.' jam'),
        ];
    }
}
