<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** Mengaktifkan kembali tautan yang dinonaktifkan. Tautan diblokir hanya dapat dibuka lewat BukaBlokirTautan (BR-26). */
class AktifkanTautan
{
    public function jalankan(TautanPendek $tautan, User $oleh): TautanPendek
    {
        Gate::forUser($oleh)->authorize('nonaktifkan', $tautan);

        return app(UbahStatusTautan::class)->terapkan($tautan, StatusTautan::Aktif, $oleh, null, false);
    }
}
