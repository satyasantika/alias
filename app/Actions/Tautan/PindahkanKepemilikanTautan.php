<?php

namespace App\Actions\Tautan;

use App\Enums\Izin;
use App\Enums\JenisKepemilikan;
use App\Events\TautanDipindahkan;
use App\Models\RiwayatKepemilikanTautan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * BR-21: transfer kepemilikan. Kode, URL tujuan, status, dan statistik tidak berubah.
 * Arah yang sah: admin bebas; pemilik pribadi → unit tempat ia anggota; pengelola: unit yang ia kelola ↔
 * tautan pribadi anggota unit tersebut dan antar-unit yang ia kelola.
 */
class PindahkanKepemilikanTautan
{
    public function jalankan(TautanPendek $tautan, User|Unit $ke, User $oleh, ?string $alasan = null): TautanPendek
    {
        Gate::forUser($oleh)->authorize('transfer', $tautan);

        return $this->terapkan($tautan, $ke, $oleh, $alasan, periksaArah: true);
    }

    /** Pemindahan tanpa otorisasi per-record (dipakai pemindahan massal yang sudah diotorisasi). */
    public function terapkan(TautanPendek $tautan, User|Unit $ke, User $oleh, ?string $alasan, bool $periksaArah, bool $periksaKuota = true, bool $umumkan = true): TautanPendek
    {
        $tautan->loadMissing(['pemilik', 'unit']);
        $dari = $tautan->jenis_kepemilikan === JenisKepemilikan::Unit ? $tautan->unit : $tautan->pemilik;

        if ($dari === null) {
            throw ValidationException::withMessages(['ke' => 'Pemilik saat ini tidak ditemukan.']);
        }

        if ($ke->is($dari)) {
            throw ValidationException::withMessages(['ke' => 'Tujuan pemindahan sama dengan pemilik saat ini.']);
        }

        if ($ke instanceof User && ! $ke->aktif) {
            throw ValidationException::withMessages(['ke' => 'Pengguna tujuan tidak aktif.']);
        }

        if ($ke instanceof Unit && ! $ke->aktif) {
            throw ValidationException::withMessages(['ke' => 'Unit tujuan tidak aktif.']);
        }

        if ($periksaArah && ! $this->bolehKe($tautan, $oleh, $ke)) {
            throw ValidationException::withMessages(['ke' => 'Anda tidak diizinkan memindahkan tautan ini ke tujuan tersebut.']);
        }

        if ($periksaKuota) {
            $this->periksaKuota($ke, $oleh);
        }

        $jenisKe = $ke instanceof Unit ? JenisKepemilikan::Unit : JenisKepemilikan::Pribadi;

        DB::transaction(function () use ($tautan, $dari, $ke, $jenisKe, $oleh, $alasan): void {
            RiwayatKepemilikanTautan::create([
                'tautan_pendek_id' => $tautan->getKey(),
                'dari_jenis' => $tautan->jenis_kepemilikan,
                'dari_pemilik_id' => $dari instanceof User ? $dari->getKey() : null,
                'dari_unit_id' => $dari instanceof Unit ? $dari->getKey() : null,
                'ke_jenis' => $jenisKe,
                'ke_pemilik_id' => $ke instanceof User ? $ke->getKey() : null,
                'ke_unit_id' => $ke instanceof Unit ? $ke->getKey() : null,
                'alasan' => $alasan !== null ? trim($alasan) : null,
                'oleh' => $oleh->getKey(),
            ]);

            $tautan->forceFill([
                'jenis_kepemilikan' => $jenisKe,
                'pemilik_id' => $ke instanceof User ? $ke->getKey() : null,
                'unit_id' => $ke instanceof Unit ? $ke->getKey() : null,
            ])->save();
        });

        $tautan->unsetRelation('pemilik')->unsetRelation('unit');

        if ($umumkan) {
            TautanDipindahkan::dispatch($tautan, $dari, $ke, $oleh);
        }

        return $tautan;
    }

    public function bolehKe(TautanPendek $tautan, User $oleh, User|Unit $ke): bool
    {
        if ($oleh->adalahAdmin()) {
            return true;
        }

        $tautan->loadMissing(['pemilik', 'unit']);

        if ($ke instanceof Unit) {
            if ($tautan->jenis_kepemilikan === JenisKepemilikan::Pribadi) {
                $pemilik = $tautan->pemilik;

                if ($tautan->milikPribadi($oleh)) {
                    return $oleh->anggotaUnit($ke);
                }

                return $pemilik !== null && $oleh->kelolaUnit($ke) && $pemilik->anggotaUnit($ke);
            }

            return $tautan->unit !== null && $oleh->kelolaUnit($tautan->unit) && $oleh->kelolaUnit($ke);
        }

        // Ke pengguna: hanya unit → pribadi anggota unit tersebut, oleh pengelolanya.
        return $tautan->jenis_kepemilikan === JenisKepemilikan::Unit
            && $tautan->unit !== null
            && $oleh->kelolaUnit($tautan->unit)
            && $ke->anggotaUnit($tautan->unit);
    }

    /**
     * Tujuan yang sah untuk tautan ini (sumber pilihan di antarmuka; sama dengan bolehKe()).
     *
     * @return array{unit: array<string, string>, pengguna: array<string, string>}
     */
    public function tujuanSah(TautanPendek $tautan, User $oleh): array
    {
        $unit = Unit::query()->where('aktif', true)->orderBy('nama')->get()
            ->filter(fn (Unit $u) => $this->bolehKeAman($tautan, $oleh, $u));

        $pengguna = User::query()->where('aktif', true)->orderBy('name')->get()
            ->filter(fn (User $u) => $this->bolehKeAman($tautan, $oleh, $u));

        return [
            'unit' => $unit->pluck('nama', 'id')->all(),
            'pengguna' => $pengguna->pluck('name', 'id')->all(),
        ];
    }

    private function bolehKeAman(TautanPendek $tautan, User $oleh, User|Unit $ke): bool
    {
        $tautan->loadMissing(['pemilik', 'unit']);
        $dari = $tautan->jenis_kepemilikan === JenisKepemilikan::Unit ? $tautan->unit : $tautan->pemilik;

        return ! $ke->is($dari) && $this->bolehKe($tautan, $oleh, $ke);
    }

    private function periksaKuota(User|Unit $ke, User $oleh): void
    {
        if ($oleh->can(Izin::TautanTanpaKuota->value)) {
            return;
        }

        if ($ke instanceof User) {
            $kuota = (int) ($ke->kuota_tautan ?? Pengaturan::ambil('kuota_bawaan_pengguna', 100));
            $terpakai = TautanPendek::query()->where('jenis_kepemilikan', 'pribadi')->where('pemilik_id', $ke->getKey())->count();
        } else {
            $kuota = (int) ($ke->kuota_tautan ?? Pengaturan::ambil('kuota_bawaan_unit', 500));
            $terpakai = TautanPendek::query()->where('jenis_kepemilikan', 'unit')->where('unit_id', $ke->getKey())->count();
        }

        if ($terpakai >= $kuota) {
            throw ValidationException::withMessages(['ke' => 'Kuota tautan pada tujuan sudah penuh.']);
        }
    }
}
