<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Models\LogLogin;
use App\Models\User;

class LogLoginPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::LogLoginLihat->value);
    }

    public function view(User $pengguna, LogLogin $log): bool
    {
        return $pengguna->can(Izin::LogLoginLihat->value);
    }

    public function create(User $pengguna): bool
    {
        return false;
    }

    public function update(User $pengguna, LogLogin $log): bool
    {
        return false;
    }

    public function delete(User $pengguna, LogLogin $log): bool
    {
        return false;
    }
}
