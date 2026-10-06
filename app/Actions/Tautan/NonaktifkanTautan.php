<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class NonaktifkanTautan
{
    public function jalankan(TautanPendek $tautan, User $oleh, ?string $alasan = null): TautanPendek
    {
        Gate::forUser($oleh)->authorize('nonaktifkan', $tautan);

        return app(UbahStatusTautan::class)->terapkan(
            $tautan, StatusTautan::Dinonaktifkan, $oleh, $alasan, ! UbahStatusTautan::pemilikTindakan($tautan, $oleh),
        );
    }
}
