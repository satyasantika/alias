<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::AuditLihat->value);
    }

    public function view(User $pengguna, Activity $activity): bool
    {
        return $pengguna->can(Izin::AuditLihat->value);
    }

    public function create(User $pengguna): bool
    {
        return false;
    }

    public function update(User $pengguna, Activity $activity): bool
    {
        return false;
    }

    public function delete(User $pengguna, Activity $activity): bool
    {
        return false;
    }
}
