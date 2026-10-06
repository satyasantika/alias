<?php

namespace App\Console\Commands;

use App\Enums\Izin;
use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Notifications\RingkasanTautanYatim;
use App\Support\Notifikasi\Penerima;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Console\Attribute\AsCommand;

/** BR-30: tautan aktif berpemilik pribadi nonaktif dilaporkan mingguan ke admin. */
#[AsCommand(name: 'alias:laporan-yatim', description: 'Kirim ringkasan tautan yatim (pemilik nonaktif) ke admin')]
class LaporanYatim extends Command
{
    public const MAKS_BARIS = 50;

    public function handle(): int
    {
        $kueri = TautanPendek::query()
            ->where('status', StatusTautan::Aktif->value)
            ->where('jenis_kepemilikan', 'pribadi')
            ->whereHas('pemilik', fn ($q) => $q->where(fn ($w) => $w->where('aktif', false)->orWhereNotNull('deleted_at')))
            ->with('pemilik');

        $total = (clone $kueri)->count();

        if ($total === 0) {
            $this->info('Tidak ada tautan yatim.');

            return self::SUCCESS;
        }

        $daftar = $kueri->orderBy('created_at')->limit(self::MAKS_BARIS)->get()
            ->map(fn (TautanPendek $t) => ['kode' => $t->kode, 'judul' => $t->judul, 'pemilik' => (string) $t->pemilik?->name])
            ->all();

        Notification::send(Penerima::pemegangIzin(Izin::ModerasiKelola->value), new RingkasanTautanYatim($daftar, $total));

        $this->info("Ringkasan {$total} tautan yatim dikirim.");

        return self::SUCCESS;
    }
}
