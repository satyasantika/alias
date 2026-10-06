<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BukaBlokirTautan
{
    public function jalankan(TautanPendek $tautan, User $oleh, ?string $alasan = null): TautanPendek
    {
        Gate::forUser($oleh)->authorize('blokir', $tautan);

        return app(UbahStatusTautan::class)->terapkan($tautan, StatusTautan::Aktif, $oleh, $alasan, false);
    }
}
