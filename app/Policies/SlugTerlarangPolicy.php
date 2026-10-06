<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Enums\JenisSlugTerlarang;
use App\Enums\Peran;
use App\Models\SlugTerlarang;
use App\Models\User;

class SlugTerlarangPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::SlugTerlarangKelola->value);
    }

    public function view(User $pengguna, SlugTerlarang $slug): bool
    {
        return $pengguna->can(Izin::SlugTerlarangKelola->value);
    }

    public function create(User $pengguna): bool
    {
        return $pengguna->can(Izin::SlugTerlarangKelola->value);
    }

    /** Entri cadangan sistem hanya dapat diubah (dinonaktifkan) super-admin. */
    public function update(User $pengguna, SlugTerlarang $slug): bool
    {
        if ($slug->jenis === JenisSlugTerlarang::CadanganSistem) {
            return $pengguna->hasRole(Peran::SuperAdmin->value);
        }

        return $pengguna->can(Izin::SlugTerlarangKelola->value);
    }

    public function delete(User $pengguna, SlugTerlarang $slug): bool
    {
        return $slug->jenis !== JenisSlugTerlarang::CadanganSistem && $pengguna->can(Izin::SlugTerlarangKelola->value);
    }
}
