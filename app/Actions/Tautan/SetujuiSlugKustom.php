<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class SetujuiSlugKustom
{
    public function jalankan(TautanPendek $tautan, User $oleh): TautanPendek
    {
        Gate::forUser($oleh)->authorize('setujui', $tautan);

        return app(UbahStatusTautan::class)->terapkan($tautan, StatusTautan::Aktif, $oleh, null, false, [
            'disetujui_oleh' => $oleh->getKey(),
            'disetujui_pada' => now(),
        ]);
    }
}
