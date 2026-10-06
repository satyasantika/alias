<?php

namespace App\Events;

use App\Models\TautanPendek;
use Illuminate\Foundation\Events\Dispatchable;

/** Dikirim saat tujuan tautan berubah menjadi "bermasalah"; pemberitahuan pemilik ditambahkan pada F8.1. */
class TujuanBermasalah
{
    use Dispatchable;

    public function __construct(public readonly TautanPendek $tautan) {}
}
