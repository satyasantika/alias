<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-04: tautan yang pernah aktif di-soft-delete (kode terkunci selamanya); yang belum pernah aktif
 * (mis. slug ditolak) dihapus permanen sehingga slug bebas.
 */
class HapusTautan
{
    public function jalankan(TautanPendek $tautan, User $oleh): void
    {
        Gate::forUser($oleh)->authorize('delete', $tautan);

        if ($tautan->status === StatusTautan::Diblokir && ! $oleh->adalahAdmin()) {
            throw ValidationException::withMessages(['status' => 'Tautan yang diblokir hanya dapat dihapus admin.']);
        }

        DB::transaction(function () use ($tautan, $oleh): void {
            if ($tautan->pertama_aktif_pada === null) {
                $tautan->forceDelete();

                return;
            }

            $tautan->forceFill(['dihapus_oleh' => $oleh->getKey()])->saveQuietly();
            $tautan->delete();
        });
    }
}
