<?php

namespace App\Events;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/** Dikirim setiap status tautan terbentuk/berubah; hanya terkirim bila transaksi berhasil di-commit. */
class StatusTautanBerubah implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly TautanPendek $tautan,
        public readonly ?StatusTautan $dari,
        public readonly StatusTautan $ke,
        public readonly ?User $oleh,
        public readonly ?string $alasan = null,
    ) {}
}
