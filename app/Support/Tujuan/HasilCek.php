<?php

namespace App\Support\Tujuan;

use App\Enums\StatusCekTujuan;

/** Hasil satu pemeriksaan tujuan. $status null = gagal (404/410/5xx/DNS/timeout/redirect berlebih). */
final readonly class HasilCek
{
    public function __construct(public ?StatusCekTujuan $status, public int $kodeHttp, public string $catatan = '') {}

    public function berhasil(): bool
    {
        return $this->status !== null;
    }
}
