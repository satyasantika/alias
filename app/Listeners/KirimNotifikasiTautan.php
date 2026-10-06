<?php

namespace App\Listeners;

use App\Enums\Izin;
use App\Enums\StatusTautan;
use App\Events\LaporanPenyalahgunaanDiterima;
use App\Events\StatusTautanBerubah;
use App\Events\TautanDipindahkan;
use App\Events\TujuanBermasalah;
use App\Models\Unit;
use App\Notifications\LaporanPenyalahgunaanBaru;
use App\Notifications\SlugKustomDiputuskan;
use App\Notifications\SlugKustomMenunggu;
use App\Notifications\TautanDiblokir;
use App\Notifications\TautanDibukaBlokir;
use App\Notifications\TautanDipindahkan as NotifikasiTautanDipindahkan;
use App\Notifications\TujuanBermasalah as NotifikasiTujuanBermasalah;
use App\Support\Notifikasi\Penerima;
use Illuminate\Support\Facades\Notification;

/** 02-ARSITEKTUR §9. Event berimplementasi ShouldDispatchAfterCommit sehingga transaksi gagal tidak mengirim apa pun. */
class KirimNotifikasiTautan
{
    public function handleStatusTautanBerubah(StatusTautanBerubah $e): void
    {
        $tautan = $e->tautan;

        match (true) {
            $e->ke === StatusTautan::MenungguPersetujuan => Notification::send(
                Penerima::pemegangIzin(Izin::TautanSetujui->value), new SlugKustomMenunggu($tautan),
            ),
            $e->dari === StatusTautan::MenungguPersetujuan && in_array($e->ke, [StatusTautan::Aktif, StatusTautan::Ditolak], true) => Notification::send(
                Penerima::gabung(Penerima::pembuat($tautan), Penerima::pemilikTautan($tautan)),
                new SlugKustomDiputuskan($tautan, $e->ke === StatusTautan::Aktif, $e->alasan),
            ),
            $e->ke === StatusTautan::Diblokir => Notification::send(Penerima::pemilikTautan($tautan), new TautanDiblokir($tautan, $e->alasan)),
            $e->dari === StatusTautan::Diblokir && $e->ke === StatusTautan::Aktif => Notification::send(Penerima::pemilikTautan($tautan), new TautanDibukaBlokir($tautan)),
            default => null,
        };
    }

    public function handleTujuanBermasalah(TujuanBermasalah $e): void
    {
        Notification::send(Penerima::pemilikTautan($e->tautan), new NotifikasiTujuanBermasalah($e->tautan));
    }

    public function handleLaporanPenyalahgunaanDiterima(LaporanPenyalahgunaanDiterima $e): void
    {
        Notification::send(Penerima::pemegangIzin(Izin::ModerasiKelola->value), new LaporanPenyalahgunaanBaru($e->laporan));
    }

    public function handleTautanDipindahkan(TautanDipindahkan $e): void
    {
        $dari = $e->dari instanceof Unit ? $e->dari->nama : $e->dari->name;
        $ke = $e->ke instanceof Unit ? $e->ke->nama : $e->ke->name;

        $lama = $e->dari instanceof Unit ? Penerima::pengelolaUnit($e->dari->getKey()) : Penerima::gabung(collect([$e->dari]));
        $baru = $e->ke instanceof Unit ? Penerima::pengelolaUnit($e->ke->getKey()) : Penerima::gabung(collect([$e->ke]));

        Notification::send(Penerima::gabung($lama, $baru), new NotifikasiTautanDipindahkan($e->tautan, $dari, $ke));
    }
}
