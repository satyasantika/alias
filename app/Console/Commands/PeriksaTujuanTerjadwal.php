<?php

namespace App\Console\Commands;

use App\Enums\StatusTautan;
use App\Jobs\PeriksaKesehatanTujuan;
use App\Models\TautanPendek;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'alias:periksa-tujuan', description: 'Antrekan pemeriksaan tujuan untuk tautan aktif yang terakhir dicek > 6 hari lalu')]
class PeriksaTujuanTerjadwal extends Command
{
    public const HARI = 6;

    public const KELOMPOK = 200;

    public function handle(): int
    {
        $jumlah = 0;

        TautanPendek::query()
            ->where('status', StatusTautan::Aktif->value)
            ->where(fn ($q) => $q->whereNull('dicek_tujuan_pada')->orWhere('dicek_tujuan_pada', '<', now()->subDays(self::HARI)))
            ->chunkById(self::KELOMPOK, function ($kelompok) use (&$jumlah): void {
                foreach ($kelompok as $tautan) {
                    PeriksaKesehatanTujuan::dispatch($tautan->getKey());
                    $jumlah++;
                }
            });

        $this->info("{$jumlah} pemeriksaan diantrekan.");

        return self::SUCCESS;
    }
}
