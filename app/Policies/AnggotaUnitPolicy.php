<?php

namespace App\Policies;

use App\Models\AnggotaUnit;
use App\Models\Unit;
use App\Models\User;

class AnggotaUnitPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can('viewAny', Unit::class);
    }

    public function view(User $pengguna, AnggotaUnit $anggota): bool
    {
        return $pengguna->can('view', $anggota->unit);
    }

    public function create(User $pengguna): bool
    {
        return $pengguna->can('unit.kelola-anggota');
    }

    public function update(User $pengguna, AnggotaUnit $anggota): bool
    {
        return $pengguna->can('kelolaAnggota', $anggota->unit);
    }

    public function delete(User $pengguna, AnggotaUnit $anggota): bool
    {
        return $pengguna->can('kelolaAnggota', $anggota->unit);
    }
}
