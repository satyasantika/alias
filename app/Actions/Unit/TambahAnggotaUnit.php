<?php

namespace App\Actions\Unit;

use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Models\AnggotaUnit;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TambahAnggotaUnit
{
    public function jalankan(Unit $unit, User $target, PeranUnit $peran, User $oleh): AnggotaUnit
    {
        Gate::forUser($oleh)->authorize('kelolaAnggota', $unit);

        if (! $target->aktif) {
            throw ValidationException::withMessages(['user_id' => 'Pengguna tidak aktif tidak dapat menjadi anggota unit.']);
        }

        return DB::transaction(function () use ($unit, $target, $peran, $oleh): AnggotaUnit {
            $anggota = AnggotaUnit::query()->firstOrNew(['unit_id' => $unit->getKey(), 'user_id' => $target->getKey()]);
            $anggota->peran_unit = $peran;
            $anggota->ditambahkan_oleh ??= $oleh->getKey();
            $anggota->save();

            if ($peran === PeranUnit::Pengelola) {
                $target->assignRole(Peran::PengelolaUnit->value);
            }

            return $anggota;
        });
    }
}
