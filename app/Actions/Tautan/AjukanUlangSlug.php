<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use App\Support\Kode\PemeriksaSlug;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** BR-25/diagram §8.1: slug ditolak dapat diganti pemilik lalu diajukan ulang. */
class AjukanUlangSlug
{
    public function jalankan(TautanPendek $tautan, string $slugBaru, User $oleh): TautanPendek
    {
        Gate::forUser($oleh)->authorize('update', $tautan);

        if ($tautan->status !== StatusTautan::Ditolak) {
            throw ValidationException::withMessages(['status' => 'Hanya tautan yang ditolak yang dapat diajukan ulang.']);
        }

        $slugBaru = PemeriksaSlug::normalisasi($slugBaru);
        if (($pesan = app(PemeriksaSlug::class)->periksa($slugBaru, $oleh, $tautan->unit, $tautan->getKey())) !== null) {
            throw ValidationException::withMessages(['slug_kustom' => $pesan]);
        }

        return app(UbahStatusTautan::class)->terapkan($tautan, StatusTautan::MenungguPersetujuan, $oleh, 'Diajukan ulang dengan slug baru.', false, [
            'kode' => $slugBaru,
            'kode_kustom' => true,
        ]);
    }
}
