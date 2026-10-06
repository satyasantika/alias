<?php

namespace App\Policies;

use App\Enums\Izin;
use App\Enums\JenisKepemilikan;
use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\User;

/** BR-20 dan PRD §4.3 (baris Tautan), termasuk catatan 3 (anggota hanya mengubah tautan unit buatannya). */
class TautanPendekPolicy
{
    public function viewAny(User $pengguna): bool
    {
        return $pengguna->can(Izin::TautanLihat->value);
    }

    public function view(User $pengguna, TautanPendek $tautan): bool
    {
        return $pengguna->can(Izin::TautanLihat->value) && $this->terlihat($pengguna, $tautan);
    }

    public function create(User $pengguna): bool
    {
        return $pengguna->can(Izin::TautanBuat->value);
    }

    public function update(User $pengguna, TautanPendek $tautan): bool
    {
        if ($tautan->status === StatusTautan::Diblokir && ! $pengguna->adalahAdmin()) {
            return false;
        }

        return $pengguna->can(Izin::TautanUbah->value) && $this->bolehKelola($pengguna, $tautan);
    }

    public function nonaktifkan(User $pengguna, TautanPendek $tautan): bool
    {
        if ($tautan->status === StatusTautan::Diblokir && ! $pengguna->can(Izin::TautanBlokir->value)) {
            return false;
        }

        return $pengguna->can(Izin::TautanNonaktifkan->value) && $this->bolehKelola($pengguna, $tautan);
    }

    public function delete(User $pengguna, TautanPendek $tautan): bool
    {
        if (! $pengguna->can(Izin::TautanHapus->value)) {
            return false;
        }

        if ($pengguna->adalahAdmin()) {
            return true;
        }

        return $tautan->jenis_kepemilikan === JenisKepemilikan::Pribadi
            ? $tautan->milikPribadi($pengguna)
            : ($tautan->unit !== null && $pengguna->kelolaUnit($tautan->unit));
    }

    /** Memulai pemindahan; arah yang sah divalidasi Action (BR-21, F4.6). */
    public function transfer(User $pengguna, TautanPendek $tautan): bool
    {
        if (! $pengguna->can(Izin::TautanTransfer->value)) {
            return false;
        }

        if ($pengguna->adalahAdmin()) {
            return true;
        }

        return $tautan->jenis_kepemilikan === JenisKepemilikan::Pribadi
            ? $tautan->milikPribadi($pengguna) || $this->pengelolaPemilikPribadi($pengguna, $tautan)
            : ($tautan->unit !== null && $pengguna->kelolaUnit($tautan->unit));
    }

    public function setujui(User $pengguna, TautanPendek $tautan): bool
    {
        return $pengguna->can(Izin::TautanSetujui->value);
    }

    public function blokir(User $pengguna, TautanPendek $tautan): bool
    {
        return $pengguna->can(Izin::TautanBlokir->value);
    }

    public function aturLanjutan(User $pengguna): bool
    {
        return $pengguna->can(Izin::TautanAturLanjutan->value);
    }

    public function restore(User $pengguna, TautanPendek $tautan): bool
    {
        return $pengguna->adalahAdmin();
    }

    public function forceDelete(User $pengguna, TautanPendek $tautan): bool
    {
        // Hanya tautan yang belum pernah aktif (BR-04) dan hanya yang berhak menghapus.
        return $tautan->pertama_aktif_pada === null && $this->delete($pengguna, $tautan);
    }

    private function terlihat(User $pengguna, TautanPendek $tautan): bool
    {
        if ($pengguna->adalahAdmin()) {
            return true;
        }

        return $tautan->jenis_kepemilikan === JenisKepemilikan::Pribadi
            ? $tautan->milikPribadi($pengguna)
            : ($tautan->unit !== null && $pengguna->anggotaUnit($tautan->unit));
    }

    /** Kelola (ubah/nonaktifkan): admin; pemilik pribadi; pengelola unit; anggota hanya tautan unit buatannya. */
    private function bolehKelola(User $pengguna, TautanPendek $tautan): bool
    {
        if ($pengguna->adalahAdmin()) {
            return true;
        }

        if ($tautan->jenis_kepemilikan === JenisKepemilikan::Pribadi) {
            return $tautan->milikPribadi($pengguna);
        }

        if ($tautan->unit === null) {
            return false;
        }

        if ($pengguna->kelolaUnit($tautan->unit)) {
            return true;
        }

        return $pengguna->anggotaUnit($tautan->unit) && $tautan->dibuat_oleh === $pengguna->getKey();
    }

    /** Pengelola unit boleh memindahkan tautan pribadi anggota unitnya (BR-21). */
    private function pengelolaPemilikPribadi(User $pengguna, TautanPendek $tautan): bool
    {
        $pemilik = $tautan->pemilik;

        return $pemilik !== null
            && $pemilik->unitAnggota()->whereIn('unit.id', $pengguna->unitDikelola()->select('unit.id'))->exists();
    }
}
