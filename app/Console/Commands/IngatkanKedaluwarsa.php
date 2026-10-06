<?php

namespace App\Console\Commands;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Notifications\TautanAkanKedaluwarsa;
use App\Support\Notifikasi\Penerima;
use App\Support\Pengaturan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Attribute\AsCommand;

/** Pengingat sekali per tautan per tanggal berakhir: tautan aktif yang aktif_sampai-nya dalam N hari. */
#[AsCommand(name: 'alias:ingatkan-kedaluwarsa', description: 'Ingatkan pemilik tentang tautan yang akan kedaluwarsa')]
class IngatkanKedaluwarsa extends Command
{
    public function handle(): int
    {
        $hari = (int) Pengaturan::ambil('hari_pengingat_kedaluwarsa', 7);
        $jumlah = 0;

        TautanPendek::query()
            ->where('status', StatusTautan::Aktif->value)
            ->whereBetween('aktif_sampai', [now(), now()->addDays($hari)])
            ->each(function (TautanPendek $tautan) use (&$jumlah): void {
                $kunci = "ingatan-kedaluwarsa:{$tautan->getKey()}:{$tautan->aktif_sampai?->timestamp}";

                if (! Cache::add($kunci, true, now()->addDays(60))) {
                    return;
                }

                Notification::send(Penerima::pemilikTautan($tautan), new TautanAkanKedaluwarsa($tautan));
                $jumlah++;
            });

        $this->info("{$jumlah} pengingat dikirim.");

        return self::SUCCESS;
    }
}
