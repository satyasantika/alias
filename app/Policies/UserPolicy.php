<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Enums\Peran;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::PenggunaLihat->value);
    }

    public function view(User $pengguna, User $target): bool
    {
        return $pengguna->can(Izin::PenggunaLihat->value);
    }

    public function create(User $pengguna): bool
    {
        return $pengguna->can(Izin::PenggunaKelola->value);
    }

    public function update(User $pengguna, User $target): bool
    {
        if ($target->hasRole(Peran::SuperAdmin->value) && ! $pengguna->can(Izin::PenggunaAturPeranAdmin->value)) {
            return false;
        }

        return $pengguna->can(Izin::PenggunaKelola->value);
    }

    public function delete(User $pengguna, User $target): bool
    {
        if ($target->hasRole(Peran::SuperAdmin->value)) {
            return false;
        }

        return $pengguna->can(Izin::PenggunaKelola->value);
    }

    public function deleteAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::PenggunaKelola->value);
    }

    public function restore(User $pengguna, User $target): bool
    {
        return $pengguna->can(Izin::PenggunaKelola->value);
    }

    public function forceDelete(User $pengguna, User $target): bool
    {
        return false;
    }
}
