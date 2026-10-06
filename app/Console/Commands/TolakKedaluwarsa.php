<?php

namespace App\Console\Commands;

use App\Actions\Tautan\TolakSlugKustom;
use App\Enums\StatusPermintaanAkses;
use App\Enums\StatusTautan;
use App\Models\PermintaanAkses;
use App\Models\TautanPendek;
use App\Support\Pengaturan;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

/** BR-25: slug menunggu > 14 hari ditolak otomatis; permintaan akses tanpa verifikasi surel > 7 hari kedaluwarsa. */
#[AsCommand(name: 'alias:tolak-kedaluwarsa', description: 'Tolak otomatis slug dan permintaan akses yang kedaluwarsa')]
class TolakKedaluwarsa extends Command
{
    public const HARI_VERIFIKASI_SUREL = 7;

    public function handle(TolakSlugKustom $tolak): int
    {
        $hari = (int) Pengaturan::ambil('hari_kedaluwarsa_persetujuan', 14);
        $slug = 0;

        TautanPendek::query()
            ->where('status', StatusTautan::MenungguPersetujuan->value)
            ->where('created_at', '<', now()->subDays($hari))
            ->each(function (TautanPendek $tautan) use ($tolak, &$slug): void {
                $tolak->jalankan($tautan, 'Kedaluwarsa tanpa keputusan', null);
                $slug++;
            });

        $permintaan = PermintaanAkses::query()
            ->where('status', StatusPermintaanAkses::MenungguVerifikasiSurel->value)
            ->where('created_at', '<', now()->subDays(self::HARI_VERIFIKASI_SUREL))
            ->update(['status' => StatusPermintaanAkses::Kedaluwarsa->value, 'updated_at' => now()]);

        $this->info("{$slug} slug ditolak, {$permintaan} permintaan akses kedaluwarsa.");

        return self::SUCCESS;
    }
}
