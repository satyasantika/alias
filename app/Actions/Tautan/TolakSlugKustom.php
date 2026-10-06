<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class TolakSlugKustom
{
    /** $oleh null = sistem (kedaluwarsa tanpa keputusan, BR-25). */
    public function jalankan(TautanPendek $tautan, string $alasan, ?User $oleh): TautanPendek
    {
        if ($oleh !== null) {
            Gate::forUser($oleh)->authorize('setujui', $tautan);
        }

        return app(UbahStatusTautan::class)->terapkan($tautan, StatusTautan::Ditolak, $oleh, $alasan, $oleh !== null);
    }
}
