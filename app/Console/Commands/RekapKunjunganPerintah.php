<?php

namespace App\Console\Commands;

use App\Actions\Kunjungan\RekapKunjungan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

#[AsCommand(name: 'alias:rekap-kunjungan', description: 'Ringkas kunjungan satu hari (bawaan: kemarin) ke tabel rekap')]
class RekapKunjunganPerintah extends Command
{
    protected $signature = 'alias:rekap-kunjungan {--tanggal= : Tanggal Y-m-d (bawaan: kemarin)}';

    public function handle(RekapKunjungan $rekap): int
    {
        try {
            $tanggal = $this->option('tanggal') ? Carbon::parse((string) $this->option('tanggal')) : now()->subDay();
            $jumlah = $rekap->jalankan($tanggal);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Rekap {$tanggal->format('Y-m-d')}: {$jumlah} tautan.");

        return self::SUCCESS;
    }
}
