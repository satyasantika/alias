<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Models\AturanDomain;
use App\Models\User;

class AturanDomainPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::AturanDomainKelola->value);
    }

    public function view(User $pengguna, AturanDomain $aturan): bool
    {
        return $pengguna->can(Izin::AturanDomainKelola->value);
    }

    public function create(User $pengguna): bool
    {
        return $pengguna->can(Izin::AturanDomainKelola->value);
    }

    public function update(User $pengguna, AturanDomain $aturan): bool
    {
        return $pengguna->can(Izin::AturanDomainKelola->value);
    }

    public function delete(User $pengguna, AturanDomain $aturan): bool
    {
        return $pengguna->can(Izin::AturanDomainKelola->value);
    }
}
