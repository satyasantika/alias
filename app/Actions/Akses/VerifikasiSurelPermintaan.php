<?php

namespace App\Actions\Akses;

use App\Enums\Izin;
use App\Enums\StatusPermintaanAkses;
use App\Models\PermintaanAkses;
use App\Models\User;
use App\Notifications\PermintaanAksesBaru;
use Illuminate\Support\Facades\Notification;

class VerifikasiSurelPermintaan
{
    /** @return bool true bila verifikasi berhasil (idempoten: permintaan yang sudah diverifikasi juga true). */
    public function jalankan(PermintaanAkses $permintaan): bool
    {
        if ($permintaan->status === StatusPermintaanAkses::Menunggu) {
            return true;
        }

        if ($permintaan->status !== StatusPermintaanAkses::MenungguVerifikasiSurel) {
            return false;
        }

        $permintaan->update([
            'status' => StatusPermintaanAkses::Menunggu,
            'email_terverifikasi_pada' => now(),
        ]);

        $admin = User::permission(Izin::AksesProses->value)->where('aktif', true)->get();
        Notification::send($admin, new PermintaanAksesBaru($permintaan));

        return true;
    }
}
