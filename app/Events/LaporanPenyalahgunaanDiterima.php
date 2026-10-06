<?php

namespace App\Events;

use App\Models\LaporanPenyalahgunaan;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Dikirim setelah laporan publik tersimpan; notifikasi ke admin ditambahkan pada F8.1. */
class LaporanPenyalahgunaanDiterima implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(public readonly LaporanPenyalahgunaan $laporan) {}
}
