<?php

namespace App\Actions\Tautan;

use App\Enums\Izin;
use App\Exceptions\UrlTujuanTidakValid;
use App\Models\User;
use App\Support\Tujuan\ValidatorUrlTujuan;
use Illuminate\Validation\ValidationException;

/** Aturan bersama BuatTautan dan UbahTautan. */
trait ValidasiTautan
{
    /** @return array{url: string, host: string, hash: string, peringatan: list<string>} */
    private function validasiTujuan(string $url, User $oleh): array
    {
        try {
            return app(ValidatorUrlTujuan::class)->validasi($url, $oleh);
        } catch (UrlTujuanTidakValid $e) {
            throw ValidationException::withMessages(['url_tujuan' => $e->getMessage()]);
        }
    }

    /**
     * Atribut non-identitas: judul, keterangan, jadwal, batas, opsi. Opsi lanjutan (301, tanpa catat) hanya
     * untuk pemegang tautan.atur-lanjutan.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function atributBersama(array $data, User $oleh, bool $penuh = true): array
    {
        $judul = trim((string) ($data['judul'] ?? ''));
        if ($judul === '' || mb_strlen($judul) > 150) {
            throw ValidationException::withMessages(['judul' => 'Judul wajib diisi (maksimal 150 karakter).']);
        }

        $keterangan = isset($data['keterangan']) ? trim((string) $data['keterangan']) : null;
        if ($keterangan !== null && mb_strlen($keterangan) > 500) {
            throw ValidationException::withMessages(['keterangan' => 'Keterangan maksimal 500 karakter.']);
        }

        $mulai = filled($data['aktif_mulai'] ?? null) ? now()->parse($data['aktif_mulai']) : null;
        $sampai = filled($data['aktif_sampai'] ?? null) ? now()->parse($data['aktif_sampai']) : null;
        if ($mulai !== null && $sampai !== null && $sampai->lessThanOrEqualTo($mulai)) {
            throw ValidationException::withMessages(['aktif_sampai' => 'Waktu berakhir harus setelah waktu mulai.']);
        }

        $batas = filled($data['batas_klik'] ?? null) ? (int) $data['batas_klik'] : null;
        if ($batas !== null && $batas < 1) {
            throw ValidationException::withMessages(['batas_klik' => 'Batas klik minimal 1.']);
        }

        $atribut = [
            'judul' => $judul,
            'keterangan' => $keterangan !== '' ? $keterangan : null,
            'aktif_mulai' => $mulai,
            'aktif_sampai' => $sampai,
            'batas_klik' => $batas,
            'sekali_pakai' => (bool) ($data['sekali_pakai'] ?? false),
            'teruskan_query' => (bool) ($data['teruskan_query'] ?? false),
        ];

        $kodeRedirect = (int) ($data['kode_status_redirect'] ?? 302);
        $catat = (bool) ($data['catat_kunjungan'] ?? true);

        if (($kodeRedirect !== 302 || ! $catat) && ! $oleh->can(Izin::TautanAturLanjutan->value)) {
            throw ValidationException::withMessages([
                'kode_status_redirect' => 'Hanya admin yang dapat memakai redirect 301 atau mematikan pencatatan kunjungan.',
            ]);
        }

        if (! in_array($kodeRedirect, [301, 302], true)) {
            throw ValidationException::withMessages(['kode_status_redirect' => 'Kode status redirect harus 301 atau 302.']);
        }

        if ($penuh || array_key_exists('kode_status_redirect', $data) || array_key_exists('catat_kunjungan', $data)) {
            $atribut['kode_status_redirect'] = $kodeRedirect;
            $atribut['catat_kunjungan'] = $catat;
        }

        return $atribut;
    }
}
