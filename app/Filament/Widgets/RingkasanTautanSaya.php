<?php

namespace App\Filament\Widgets;

use App\Enums\Izin;
use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Support\Analitik\StatistikAgregat;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RingkasanTautanSaya extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Tautan saya';

    public static function canView(): bool
    {
        $pengguna = auth()->user();

        return $pengguna !== null && $pengguna->can(Izin::TautanLihat->value) && ! $pengguna->adalahAdmin();
    }

    /** @return array<int, Stat> */
    protected function getStats(): array
    {
        $pengguna = auth()->user();
        $saya = fn () => TautanPendek::query()->where('jenis_kepemilikan', 'pribadi')->where('pemilik_id', $pengguna->getKey());

        return [
            Stat::make('Tautan aktif', $saya()->where('status', StatusTautan::Aktif->value)->count())->color('success'),
            Stat::make('Menunggu persetujuan', $saya()->where('status', StatusTautan::MenungguPersetujuan->value)->count())->color('warning'),
            Stat::make('Klik 30 hari terakhir', (new StatistikAgregat($pengguna))->klikTautanSaya(30)),
        ];
    }
}
