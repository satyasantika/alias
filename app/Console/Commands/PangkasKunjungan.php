<?php

namespace App\Console\Commands;

use App\Support\Pengaturan;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Attribute\AsCommand;

/** BR-18: hapus kunjungan manusia > retensi dan bot > 30 hari, HANYA untuk tanggal yang sudah direkap. */
#[AsCommand(name: 'alias:pangkas-kunjungan', description: 'Pangkas kunjungan lama yang sudah direkap (retensi BR-18)')]
class PangkasKunjungan extends Command
{
    public function handle(): int
    {
        $bulan = (int) Pengaturan::ambil('retensi_kunjungan_bulan', 12);
        $hariBot = (int) Pengaturan::ambil('retensi_kunjungan_bot_hari', 30);

        $manusia = $this->pangkas(false, now()->subMonthsNoOverflow($bulan)->startOfDay()->format('Y-m-d H:i:s'));
        $bot = $this->pangkas(true, now()->subDays($hariBot)->startOfDay()->format('Y-m-d H:i:s'));

        $this->info("Dipangkas: {$manusia} kunjungan manusia, {$bot} kunjungan bot.");

        return self::SUCCESS;
    }

    private function pangkas(bool $bot, string $batas): int
    {
        $total = 0;

        do {
            $id = DB::table('kunjungan_tautan as k')
                ->where('k.bot', $bot)
                ->where('k.dikunjungi_pada', '<', $batas)
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('rekap_kunjungan_harian as r')
                    ->whereColumn('r.tautan_pendek_id', 'k.tautan_pendek_id')
                    ->whereRaw('r.tanggal = date(k.dikunjungi_pada)'))
                ->limit(2000)
                ->pluck('k.id');

            $dihapus = DB::table('kunjungan_tautan')->whereIn('id', $id)->delete();
            $total += $dihapus;
        } while ($dihapus > 0);

        return $total;
    }
}
