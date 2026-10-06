<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::PenggunaLihat->value);
    }

    public function view(User $pengguna, User $target): bool
    {
        if ($pengguna->can(Izin::PenggunaKelola->value)) {
            return true;
        }

        // Pengelola unit: hanya anggota unit yang ia kelola.
        return $pengguna->can(Izin::PenggunaLihat->value)
            && $target->unitAnggota()->whereIn('unit.id', $pengguna->unitDikelola()->select('unit.id'))->exists();
    }

    public function create(User $pengguna): bool
    {
        return $pengguna->can(Izin::PenggunaKelola->value);
    }

    public function update(User $pengguna, User $target): bool
    {
        if ($target->wajibMfa() && ! $pengguna->can(Izin::PenggunaAturPeranAdmin->value)) {
            return false;
        }

        return $pengguna->can(Izin::PenggunaKelola->value);
    }

    /** BR-30: akun bertautan dan super admin tidak pernah dihapus permanen. */
    public function delete(User $pengguna, User $target): bool
    {
        return false;
    }

    public function deleteAny(User $pengguna): bool
    {
        return false;
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
