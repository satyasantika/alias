<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Models\PermintaanAkses;
use App\Models\User;

class PermintaanAksesPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::AksesProses->value);
    }

    public function view(User $pengguna, PermintaanAkses $permintaan): bool
    {
        return $pengguna->can(Izin::AksesProses->value);
    }

    public function create(User $pengguna): bool
    {
        return false;
    }

    public function update(User $pengguna, PermintaanAkses $permintaan): bool
    {
        return $pengguna->can(Izin::AksesProses->value);
    }

    public function delete(User $pengguna, PermintaanAkses $permintaan): bool
    {
        return false;
    }
}
