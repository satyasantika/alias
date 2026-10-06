<?php

namespace App\Actions\Pengguna;

use App\Actions\Tautan\PindahkanSemuaTautanPengguna;
use App\Enums\Peran;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** BR-30/BR-33: akun tidak dihapus, hanya dinonaktifkan; tautan dapat dialihkan massal. */
class NonaktifkanPengguna
{
    public function jalankan(User $target, User $oleh, User|Unit|null $alihkanKe = null): void
    {
        Gate::forUser($oleh)->authorize('update', $target);

        if ($target->is($oleh)) {
            throw ValidationException::withMessages(['aktif' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }

        if ($target->hasRole(Peran::SuperAdmin->value) && app(AturPeranPengguna::class)->superAdminAktifLain($target) === 0) {
            throw ValidationException::withMessages(['aktif' => 'Super admin aktif terakhir tidak dapat dinonaktifkan.']);
        }

        DB::transaction(function () use ($target, $oleh, $alihkanKe): void {
            if ($alihkanKe !== null) {
                app(PindahkanSemuaTautanPengguna::class)->jalankan($target, $alihkanKe, $oleh);
            }

            $target->forceFill(['aktif' => false])->save();
        });
    }
}
