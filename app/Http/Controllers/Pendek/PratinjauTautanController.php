<?php

namespace App\Http\Controllers\Pendek;

use App\Enums\JenisKepemilikan;
use App\Enums\StatusTautan;
use App\Http\Controllers\Controller;
use App\Models\TautanPendek;
use App\Support\Kode\PencariTautan;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Response;

/** BR-27: pratinjau tanpa mencatat kunjungan dan tanpa mengonsumsi klik. */
class PratinjauTautanController extends Controller
{
    public function __construct(private readonly PencariTautan $pencari) {}

    public function __invoke(string $kode): Response
    {
        $kode = rtrim($kode, '+');
        $tautan = $this->pencari->cari($kode);

        if ($tautan === null || in_array($tautan->status, [StatusTautan::MenungguPersetujuan, StatusTautan::Ditolak], true)) {
            return $this->kirim(response()->view('pendek.galat', [
                'judul' => 'Tautan tidak ditemukan',
                'pesan' => 'Kode tautan yang Anda buka tidak ada atau salah ketik.',
                'kode' => $kode,
                'status' => 404,
            ], 404));
        }

        $tampilkanTujuan = ! $tautan->trashed() && in_array($tautan->status, [StatusTautan::Aktif, StatusTautan::Dinonaktifkan], true);

        return $this->kirim(response()->view('pendek.pratinjau', [
            'tautan' => $tautan,
            'tampilkanTujuan' => $tampilkanTujuan,
            'dapatDilanjutkan' => $tampilkanTujuan && $tautan->status === StatusTautan::Aktif,
            'diblokir' => $tautan->status === StatusTautan::Diblokir,
            'pemilik' => $this->namaPemilik($tautan),
            'qr' => $this->qrDataUri($tautan),
        ]));
    }

    private function namaPemilik(TautanPendek $tautan): string
    {
        return $tautan->jenis_kepemilikan === JenisKepemilikan::Unit
            ? (string) ($tautan->unit()->value('nama') ?? 'Unit')
            : 'Pribadi';
    }

    private function qrDataUri(TautanPendek $tautan): string
    {
        $svg = (new Builder(
            writer: new SvgWriter,
            data: $tautan->url_pendek,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 220,
            margin: 8,
        ))->build()->getString();

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }

    private function kirim(Response $respons): Response
    {
        $respons->headers->set('X-Frame-Options', 'DENY');
        $respons->headers->set('Cache-Control', 'no-store, private, max-age=0');

        return $respons;
    }
}
