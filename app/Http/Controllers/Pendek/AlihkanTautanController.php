<?php

namespace App\Http\Controllers\Pendek;

use App\Enums\StatusEfektifTautan;
use App\Enums\StatusTautan;
use App\Http\Controllers\Controller;
use App\Jobs\CatatKunjungan;
use App\Models\TautanPendek;
use App\Support\Kode\PencariTautan;
use App\Support\Kunjungan\AnonimisasiIp;
use App\Support\Kunjungan\DeteksiBotCepat;
use App\Support\Kunjungan\GaramHarian;
use App\Support\Kunjungan\HostPerujuk;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

/** Inti layanan: /{kode} → 302 (BR-09–BR-12). Tetap ringan: satu query berindeks unik. */
class AlihkanTautanController extends Controller
{
    public function __construct(private readonly PencariTautan $pencari) {}

    public function __invoke(Request $request, string $kode): Response|RedirectResponse
    {
        $tautan = $this->pencari->cari($kode);

        if ($tautan === null) {
            return $this->galat(404, 'Tautan tidak ditemukan', 'Kode tautan yang Anda buka tidak ada atau salah ketik.', $kode);
        }

        if ($respons = $this->tolakBilaTidakDapatDialihkan($tautan, $kode)) {
            return $respons;
        }

        if (! $request->isMethod('HEAD')) {
            $this->catatKunjungan($request, $tautan);
        }

        return $this->alihkan($tautan);
    }

    /**
     * BR-16/17: IP dianonimkan di sini; yang masuk antrean hanya IP anonim + HMAC. Kegagalan antrean/Redis tidak
     * boleh menggagalkan pengalihan (NFR ketersediaan).
     */
    private function catatKunjungan(Request $request, TautanPendek $tautan, bool $sudahDikonsumsi = false): void
    {
        try {
            $ip = (string) $request->ip();
            $agen = $request->userAgent();

            app(Dispatcher::class)->dispatch(new CatatKunjungan(
                tautanId: $tautan->getKey(),
                dikunjungiPada: now()->toIso8601String(),
                ipAnonim: AnonimisasiIp::anonimkan($ip),
                ipHash: GaramHarian::hashIp($ip),
                userAgent: $agen !== null ? mb_substr($agen, 0, 512) : null,
                perujukHost: HostPerujuk::dari($request->headers->get('referer')),
                botCepat: DeteksiBotCepat::apakahBot($agen),
                catatDetail: (bool) $tautan->catat_kunjungan,
                sudahDikonsumsi: $sudahDikonsumsi,
            ));
        } catch (Throwable $e) {
            Log::warning('Kunjungan tidak tercatat (antrean/Redis bermasalah)', ['tautan' => $tautan->getKey(), 'galat' => $e::class]);
        }
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
