<?php

namespace App\Console\Commands;

use App\Support\Kode\SinkronPrefiksUnit;
use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'alias:sinkron-prefiks', description: 'Sinkronkan prefiks slug unit ke daftar slug terlarang (BR-22)')]
class SinkronPrefiks extends Command
{
    public function handle(): int
    {
        $jumlah = SinkronPrefiksUnit::sinkronSemua();
        $this->info("{$jumlah} unit disinkronkan (namespace_unit=".(config('alias.namespace_unit') ? 'aktif' : 'nonaktif').').');

        return self::SUCCESS;
    }
}
