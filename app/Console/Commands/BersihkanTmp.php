<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Attribute\AsCommand;

/** 02 §10: berkas sementara (ekspor/impor) dihapus setelah 24 jam. `.gitignore` dipertahankan. */
#[AsCommand(name: 'alias:bersihkan-tmp', description: 'Hapus berkas storage/app/tmp yang berumur > 24 jam')]
class BersihkanTmp extends Command
{
    public const JAM = 24;

    public function handle(): int
    {
        $folder = storage_path('app/tmp');
        $dihapus = 0;

        if (File::isDirectory($folder)) {
            foreach (File::allFiles($folder, true) as $berkas) {
                if ($berkas->getFilename() === '.gitignore') {
                    continue;
                }

                if ($berkas->getMTime() < now()->subHours(self::JAM)->getTimestamp()) {
                    File::delete($berkas->getPathname());
                    $dihapus++;
                }
            }
        }

        $this->info("{$dihapus} berkas sementara dihapus.");

        return self::SUCCESS;
    }
}
