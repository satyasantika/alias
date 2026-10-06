<?php

namespace App\Actions\Unit;

use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class KeluarkanAnggotaUnit
{
    public function jalankan(Unit $unit, User $target, User $oleh): void
    {
        Gate::forUser($oleh)->authorize('kelolaAnggota', $unit);

        $keanggotaan = $unit->keanggotaan()->where('user_id', $target->getKey())->first();

        if ($keanggotaan === null) {
            return;
        }

        if ($keanggotaan->peran_unit === PeranUnit::Pengelola && $unit->jumlahPengelola() <= 1) {
            throw ValidationException::withMessages([
                'user_id' => 'Pengelola terakhir tidak dapat dikeluarkan. Tunjuk pengelola lain terlebih dahulu.',
            ]);
        }

        DB::transaction(function () use ($keanggotaan, $target): void {
            $keanggotaan->delete();

            if (! $target->unitDikelola()->exists()) {
                $target->removeRole(Peran::PengelolaUnit->value);
            }
        });
    }
}
