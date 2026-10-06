<?php

namespace App\Actions\Akses;

use App\Enums\Izin;
use App\Enums\StatusPermintaanAkses;
use App\Models\PermintaanAkses;
use App\Models\User;
use App\Notifications\PermintaanAksesDiputuskan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class TolakPermintaanAkses
{
    public function jalankan(PermintaanAkses $permintaan, string $alasan, User $oleh): void
    {
        Gate::forUser($oleh)->authorize(Izin::AksesProses->value);

        if (mb_strlen(trim($alasan)) < 5) {
            throw ValidationException::withMessages(['catatan' => 'Alasan penolakan wajib diisi.']);
        }

        if ($permintaan->status !== StatusPermintaanAkses::Menunggu) {
            throw ValidationException::withMessages(['status' => 'Hanya permintaan berstatus "menunggu" yang dapat ditolak.']);
        }

        $permintaan->update([
            'status' => StatusPermintaanAkses::Ditolak,
            'catatan' => trim($alasan),
            'diproses_oleh' => $oleh->getKey(),
            'diproses_pada' => now(),
        ]);

        Notification::route('mail', $permintaan->email)->notify(new PermintaanAksesDiputuskan($permintaan));
    }
}
