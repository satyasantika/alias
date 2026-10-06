<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class BlokirTautan
{
    /** $oleh null = sistem (blokir otomatis BR-37). */
    public function jalankan(TautanPendek $tautan, string $alasan, ?User $oleh): TautanPendek
    {
        if ($oleh !== null) {
            Gate::forUser($oleh)->authorize('blokir', $tautan);
        }

        return app(UbahStatusTautan::class)->terapkan($tautan, StatusTautan::Diblokir, $oleh, $alasan, true);
    }
}
