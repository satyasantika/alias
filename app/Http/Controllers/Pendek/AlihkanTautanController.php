<?php

namespace App\Http\Controllers\Pendek;

use App\Enums\StatusEfektifTautan;
use App\Enums\StatusTautan;
use App\Http\Controllers\Controller;
use App\Models\TautanPendek;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

/** Inti layanan: /{kode} → 302 (BR-09–BR-12). Tetap ringan: satu query berindeks unik. */
class AlihkanTautanController extends Controller
{
    private const KOLOM = [
        'id', 'kode', 'kode_kustom', 'judul', 'url_tujuan', 'status', 'alasan_status', 'aktif_mulai', 'aktif_sampai',
        'batas_klik', 'sekali_pakai', 'dipakai_pada', 'jumlah_klik', 'teruskan_query', 'catat_kunjungan',
        'kode_status_redirect', 'kata_sandi_hash', 'deleted_at',
    ];

    public function __invoke(Request $request, string $kode): Response|RedirectResponse
    {
        $tautan = $this->cari($kode);

        if ($tautan === null) {
            return $this->galat(404, 'Tautan tidak ditemukan', 'Kode tautan yang Anda buka tidak ada atau salah ketik.', $kode);
        }

        if ($respons = $this->tolakBilaTidakDapatDialihkan($tautan, $kode)) {
            return $respons;
        }

        return $this->alihkan($tautan);
    }

    /** BR-12: pencocokan persis; fallback huruf kecil hanya untuk slug kustom. */
    private function cari(string $kode): ?TautanPendek
    {
        $tautan = TautanPendek::withTrashed()->select(self::KOLOM)->where('kode', $kode)->first();

        if ($tautan === null && $kode !== strtolower($kode)) {
            $tautan = TautanPendek::withTrashed()->select(self::KOLOM)
                ->where('kode', strtolower($kode))->where('kode_kustom', true)->first();
        }

        return $tautan;
    }

    /** BR-10 dan BR-11. */
    private function tolakBilaTidakDapatDialihkan(TautanPendek $tautan, string $kode): ?Response
    {
        if ($tautan->trashed()) {
            return $this->galat(410, 'Tautan sudah dihapus', 'Pemilik telah menghapus tautan ini.', $kode);
        }

        return match ($tautan->status) {
            StatusTautan::MenungguPersetujuan, StatusTautan::Ditolak => $this->galat(404, 'Tautan tidak ditemukan', 'Kode tautan yang Anda buka tidak ada atau salah ketik.', $kode),
            StatusTautan::Dinonaktifkan => $this->galat(410, 'Tautan dinonaktifkan', 'Pemilik menonaktifkan tautan ini untuk sementara.', $kode),
            StatusTautan::Diblokir => $this->galat(410, 'Tautan diblokir', 'Tautan dinonaktifkan karena melanggar ketentuan.', $kode),
            StatusTautan::Aktif => $this->tolakBerdasarkanStatusEfektif($tautan, $kode),
        };
    }

    private function tolakBerdasarkanStatusEfektif(TautanPendek $tautan, string $kode): ?Response
    {
        return match ($tautan->statusEfektif()) {
            StatusEfektifTautan::Terjadwal => $this->galat(404, 'Tautan belum aktif', 'Tautan ini belum dibuka. Silakan coba lagi nanti.', $kode),
            StatusEfektifTautan::Kedaluwarsa => $this->galat(410, 'Tautan sudah kedaluwarsa', 'Masa berlaku tautan ini sudah berakhir.', $kode),
            StatusEfektifTautan::Habis => $this->galat(410, 'Kuota klik tautan ini sudah habis', 'Tautan ini sudah mencapai batas pemakaian.', $kode),
            StatusEfektifTautan::TidakTersedia => $this->galat(404, 'Tautan tidak ditemukan', 'Kode tautan yang Anda buka tidak ada atau salah ketik.', $kode),
            StatusEfektifTautan::DapatDialihkan => null,
        };
    }

    private function alihkan(TautanPendek $tautan): RedirectResponse
    {
        $tujuan = $tautan->url_tujuan;

        // Pertahanan berlapis: tujuan sudah tervalidasi saat disimpan; tolak bila ada CR/LF.
        abort_if(preg_match('/[\r\n]/', $tujuan) === 1, 500);

        $respons = new RedirectResponse($tujuan, $tautan->kode_status_redirect);
        $respons->headers->set('Cache-Control', 'no-store, private, max-age=0');

        return $respons;
    }

    private function galat(int $status, string $judul, string $pesan, string $kode): Response
    {
        $respons = response()->view('pendek.galat', [
            'judul' => $judul,
            'pesan' => $pesan,
            'kode' => $kode,
            'status' => $status,
        ], $status);
        $respons->headers->set('Cache-Control', 'no-store, private, max-age=0');

        return $respons;
    }
}
