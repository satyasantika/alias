<?php

namespace App\Events;

use App\Models\LaporanPenyalahgunaan;
use Illuminate\Foundation\Events\Dispatchable;

/** Dikirim setelah laporan publik tersimpan; notifikasi ke admin ditambahkan pada F8.1. */
class LaporanPenyalahgunaanDiterima
{
    use Dispatchable;

    public function __construct(public readonly LaporanPenyalahgunaan $laporan) {}
}
