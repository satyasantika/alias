<?php

namespace App\Actions\Akses;

use App\Actions\Pengguna\BuatPengguna;
use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Izin;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Enums\StatusPermintaanAkses;
use App\Models\PermintaanAkses;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\PermintaanAksesDiputuskan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class SetujuiPermintaanAkses
{
    public function jalankan(PermintaanAkses $permintaan, Peran $peran, ?Unit $unit, User $oleh): User
    {
        Gate::forUser($oleh)->authorize(Izin::AksesProses->value);

        if ($permintaan->status !== StatusPermintaanAkses::Menunggu) {
            throw ValidationException::withMessages(['status' => 'Hanya permintaan berstatus "menunggu" yang dapat disetujui.']);
        }

        if ($peran->peranAdmin()) {
            throw ValidationException::withMessages(['peran' => 'Peran admin tidak dapat diberikan lewat permintaan akses.']);
        }

        $user = DB::transaction(function () use ($permintaan, $peran, $unit, $oleh): User {
            $user = app(BuatPengguna::class)->jalankan([
                'name' => $permintaan->nama,
                'email' => $permintaan->email,
                'nip' => $permintaan->nip,
                'unit_id' => $unit?->getKey(),
                'peran' => [$peran->value],
            ], $oleh);

            if ($unit !== null) {
                app(TambahAnggotaUnit::class)->jalankan(
                    $unit, $user, $peran === Peran::PengelolaUnit ? PeranUnit::Pengelola : PeranUnit::Anggota, $oleh,
                );
            }

            $permintaan->update([
                'status' => StatusPermintaanAkses::Disetujui,
                'diproses_oleh' => $oleh->getKey(),
                'diproses_pada' => now(),
                'user_id' => $user->getKey(),
            ]);

            return $user;
        });

        Notification::route('mail', $permintaan->email)->notify(new PermintaanAksesDiputuskan($permintaan));

        return $user;
    }
}
