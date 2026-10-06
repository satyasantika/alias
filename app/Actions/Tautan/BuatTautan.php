<?php

namespace App\Actions\Tautan;

use App\Enums\Izin;
use App\Enums\JenisKepemilikan;
use App\Enums\Peran;
use App\Enums\StatusTautan;
use App\Jobs\PeriksaKesehatanTujuan;
use App\Models\RiwayatStatusTautan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Support\Kode\PembangkitKode;
use App\Support\Kode\PemeriksaSlug;
use App\Support\Pengaturan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * F4.4: membuat tautan (kode acak atau slug kustom) dengan kepemilikan, kuota (BR-23), laju (BR-24),
 * status awal (BR-25), dan validasi URL (BR-05–08).
 */
class BuatTautan
{
    use ValidasiTautan;

    /**
     * @param  array<string, mixed>  $data
     */
    public function jalankan(array $data, User $oleh): TautanPendek
    {
        Gate::forUser($oleh)->authorize('create', TautanPendek::class);

        $this->batasiLaju($oleh);

        $jenisMasukan = $data['jenis_kepemilikan'] ?? JenisKepemilikan::Pribadi;
        $jenis = $jenisMasukan instanceof JenisKepemilikan ? $jenisMasukan : JenisKepemilikan::from($jenisMasukan);
        $unit = $jenis === JenisKepemilikan::Unit ? $this->unitSah($data['unit_id'] ?? null, $oleh) : null;

        $this->periksaKuota($oleh, $jenis, $unit);

        $tujuan = $this->validasiTujuan((string) ($data['url_tujuan'] ?? ''), $oleh);
        $atribut = $this->atributBersama($data, $oleh);

        $slug = filled($data['slug_kustom'] ?? null) ? PemeriksaSlug::normalisasi((string) $data['slug_kustom']) : null;
        if ($slug !== null) {
            if (! $oleh->can(Izin::TautanSlugKustom->value)) {
                throw ValidationException::withMessages(['slug_kustom' => 'Anda tidak diizinkan memakai slug kustom.']);
            }
            if (($pesan = app(PemeriksaSlug::class)->periksa($slug, $oleh, $unit)) !== null) {
                throw ValidationException::withMessages(['slug_kustom' => $pesan]);
            }
        }

        $status = $this->statusAwal($slug, $oleh, $unit);

        for ($percobaan = 0; $percobaan < 3; $percobaan++) {
            $kode = $slug ?? app(PembangkitKode::class)->buat();

            try {
                $tautan = DB::transaction(function () use ($kode, $slug, $tujuan, $atribut, $jenis, $unit, $oleh, $status): TautanPendek {
                    $tautan = new TautanPendek([
                        ...$atribut,
                        'kode' => $kode,
                        'kode_kustom' => $slug !== null,
                        'url_tujuan' => $tujuan['url'],
                        'host_tujuan' => $tujuan['host'],
                        'url_tujuan_hash' => $tujuan['hash'],
                        'jenis_kepemilikan' => $jenis,
                        'pemilik_id' => $jenis === JenisKepemilikan::Pribadi ? $oleh->getKey() : null,
                        'unit_id' => $unit?->getKey(),
                        'dibuat_oleh' => $oleh->getKey(),
                    ]);
                    $tautan->forceFill([
                        'status' => $status,
                        'pertama_aktif_pada' => $status === StatusTautan::Aktif ? now() : null,
                    ])->save();

                    RiwayatStatusTautan::create([
                        'tautan_pendek_id' => $tautan->getKey(),
                        'dari_status' => null,
                        'ke_status' => $status,
                        'oleh' => $oleh->getKey(),
                    ]);

                    return $tautan;
                });

                PeriksaKesehatanTujuan::untuk($tautan);

                return $tautan;
            } catch (UniqueConstraintViolationException $e) {
                // Balapan kode acak: ulangi. Untuk slug kustom berarti baru saja dipakai orang lain.
                if ($slug !== null) {
                    throw ValidationException::withMessages(['slug_kustom' => 'Slug sudah dipakai.']);
                }
            }
        }

        throw ValidationException::withMessages(['kode' => 'Gagal membuat kode unik; coba lagi.']);
    }

    private function batasiLaju(User $oleh): void
    {
        [$maks, $menit] = config('alias.batas_laju.buat-tautan');
        $kunci = 'buat-tautan:'.$oleh->getKey();

        if (! RateLimiter::attempt($kunci, $maks, fn () => true, $menit * 60)) {
            $detik = RateLimiter::availableIn($kunci);

            throw ValidationException::withMessages([
                'url_tujuan' => 'Terlalu banyak tautan dibuat. Coba lagi dalam '.ceil($detik / 60).' menit.',
            ]);
        }
    }

    private function unitSah(?string $unitId, User $oleh): Unit
    {
        $unit = $unitId ? Unit::query()->where('aktif', true)->find($unitId) : null;

        if ($unit === null) {
            throw ValidationException::withMessages(['unit_id' => 'Pilih unit yang valid.']);
        }

        if (! $oleh->adalahAdmin() && ! $oleh->anggotaUnit($unit)) {
            throw ValidationException::withMessages(['unit_id' => 'Anda bukan anggota unit tersebut.']);
        }

        return $unit;
    }

    /** BR-23. */
    private function periksaKuota(User $oleh, JenisKepemilikan $jenis, ?Unit $unit): void
    {
        if ($oleh->can(Izin::TautanTanpaKuota->value)) {
            return;
        }

        if ($jenis === JenisKepemilikan::Pribadi) {
            $kuota = (int) ($oleh->kuota_tautan ?? Pengaturan::ambil('kuota_bawaan_pengguna', 100));
            $terpakai = TautanPendek::query()->where('jenis_kepemilikan', 'pribadi')->where('pemilik_id', $oleh->getKey())->count();
            $nama = 'Kuota tautan pribadi Anda';
        } else {
            $kuota = (int) ($unit->kuota_tautan ?? Pengaturan::ambil('kuota_bawaan_unit', 500));
            $terpakai = TautanPendek::query()->where('jenis_kepemilikan', 'unit')->where('unit_id', $unit->getKey())->count();
            $nama = "Kuota tautan unit {$unit->nama}";
        }

        if ($terpakai >= $kuota) {
            throw ValidationException::withMessages(['url_tujuan' => "{$nama} sudah habis ({$kuota} tautan). Hapus tautan lama atau hubungi admin."]);
        }
    }

    /** BR-25 dan BR-22. */
    private function statusAwal(?string $slug, User $oleh, ?Unit $unit): StatusTautan
    {
        if ($slug === null || ! Pengaturan::ambil('slug_kustom_perlu_persetujuan', true)) {
            return StatusTautan::Aktif;
        }

        if ($oleh->hasAnyRole([Peran::SuperAdmin->value, Peran::AdminAlias->value, Peran::PengelolaUnit->value])) {
            return StatusTautan::Aktif;
        }

        if (config('alias.namespace_unit')) {
            $punyaPrefiks = $oleh->unitAnggota()->whereNotNull('prefiks_slug')->get(['unit.id', 'unit.prefiks_slug'])
                ->contains(fn (Unit $u) => str_starts_with($slug, $u->prefiks_slug.'-'));
            if ($punyaPrefiks) {
                return StatusTautan::Aktif;
            }
        }

        return StatusTautan::MenungguPersetujuan;
    }
}
