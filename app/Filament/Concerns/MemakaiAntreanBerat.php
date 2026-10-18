<?php

namespace App\Filament\Concerns;

/**
 * Profil Ringan (STANDAR-TEKNIS §1b): impor dan ekspor Filament berjalan di koneksi `database-berat`
 * (antrean `berat`, retry_after 960 detik, worker --timeout=900), bukan di antrean `default`.
 */
trait MemakaiAntreanBerat
{
    public function getJobConnection(): ?string
    {
        return config('queue.berat');
    }

    public function getJobQueue(): ?string
    {
        return 'berat';
    }
}
