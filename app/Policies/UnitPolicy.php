<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Models\Unit;
use App\Models\User;

class UnitPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::UnitLihat->value);
    }

    public function view(User $pengguna, Unit $unit): bool
    {
        return $pengguna->can(Izin::UnitKelola->value) || $pengguna->anggotaUnit($unit);
    }

    public function create(User $pengguna): bool
    {
        return $pengguna->can(Izin::UnitKelola->value);
    }

    public function update(User $pengguna, Unit $unit): bool
    {
        return $pengguna->can(Izin::UnitKelola->value);
    }

    public function delete(User $pengguna, Unit $unit): bool
    {
        return $pengguna->can(Izin::UnitKelola->value);
    }

    public function restore(User $pengguna, Unit $unit): bool
    {
        return $pengguna->can(Izin::UnitKelola->value);
    }

    /** Tambah/keluarkan anggota & pengelola unit. */
    public function kelolaAnggota(User $pengguna, Unit $unit): bool
    {
        if (! $pengguna->can(Izin::UnitKelolaAnggota->value)) {
            return false;
        }

        return $pengguna->can(Izin::UnitKelola->value) || $pengguna->kelolaUnit($unit);
    }
}
