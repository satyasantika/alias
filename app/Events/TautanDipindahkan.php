<?php

namespace App\Events;

use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Dikirim setelah kepemilikan tautan berpindah; pemberitahuan dibuat pada F8.1. */
class TautanDipindahkan implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly TautanPendek $tautan,
        public readonly User|Unit $dari,
        public readonly User|Unit $ke,
        public readonly User $oleh,
    ) {}
}
