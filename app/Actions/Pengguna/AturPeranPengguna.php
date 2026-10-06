<?php

namespace App\Actions\Pengguna;

use App\Enums\Izin;
use App\Enums\Peran;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** BR-33: peran admin hanya oleh pemegang pengguna.atur-peran-admin; super-admin aktif terakhir tidak dapat dicabut. */
class AturPeranPengguna
{
    /** @param  list<string>  $peranBaru */
    public function jalankan(User $target, array $peranBaru, User $oleh): void
    {
        $peranBaru = array_values(array_unique($peranBaru));
        $lama = $target->roles->pluck('name')->all();
        $berubah = array_merge(array_diff($peranBaru, $lama), array_diff($lama, $peranBaru));

        foreach ($berubah as $nama) {
            if (Peran::tryFrom($nama)?->peranAdmin() && ! $oleh->can(Izin::PenggunaAturPeranAdmin->value)) {
                throw ValidationException::withMessages(['roles' => 'Hanya super admin yang dapat mengatur peran admin.']);
            }
        }

        if (in_array(Peran::SuperAdmin->value, $lama, true)
            && ! in_array(Peran::SuperAdmin->value, $peranBaru, true)
            && $this->superAdminAktifLain($target) === 0) {
            throw ValidationException::withMessages(['roles' => 'Super admin aktif terakhir tidak dapat dicabut perannya.']);
        }

        DB::transaction(fn () => $target->syncRoles($peranBaru));
    }

    public function superAdminAktifLain(User $kecuali): int
    {
        return User::role(Peran::SuperAdmin->value)->where('aktif', true)->whereKeyNot($kecuali->getKey())->count();
    }
}
