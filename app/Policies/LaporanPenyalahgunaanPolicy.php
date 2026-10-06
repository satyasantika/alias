<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Models\LaporanPenyalahgunaan;
use App\Models\User;

class LaporanPenyalahgunaanPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::ModerasiKelola->value);
    }

    public function view(User $pengguna, LaporanPenyalahgunaan $laporan): bool
    {
        return $pengguna->can(Izin::ModerasiKelola->value);
    }

    public function create(User $pengguna): bool
    {
        return false;
    }

    public function update(User $pengguna, LaporanPenyalahgunaan $laporan): bool
    {
        return $pengguna->can(Izin::ModerasiKelola->value);
    }

    public function delete(User $pengguna, LaporanPenyalahgunaan $laporan): bool
    {
        return false;
    }
}
