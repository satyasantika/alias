<?php

namespace App\Listeners;

use Filament\Actions\Imports\Events\ImportCompleted;
use Illuminate\Support\Facades\File;

/** Satu-satunya unggahan yang diizinkan adalah CSV impor; dibuang setelah selesai diproses (02 §10). */
class HapusBerkasImpor
{
    public function handle(ImportCompleted $event): void
    {
        $jalur = $event->getImport()->file_path;

        if (is_string($jalur) && $jalur !== '' && File::isFile($jalur)) {
            File::delete($jalur);
        }
    }
}
