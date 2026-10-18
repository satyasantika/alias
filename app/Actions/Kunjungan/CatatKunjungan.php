<?php

namespace App\Actions\Kunjungan;

use App\Enums\JenisPerangkat;
use App\Models\KunjunganTautan;
use App\Models\TautanPendek;
use Carbon\Carbon;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;

/**
 * Mencatat kunjungan anonim (BR-16/17). Dijalankan lewat defer() setelah respons pengalihan terkirim
 * (STANDAR-TEKNIS §1b), bukan job antrean: data (termasuk user agent untuk diurai) hanya ada di memori proses
 * dan tidak pernah masuk tabel `jobs`. Kunjungan bisa hilang bila proses PHP mati di tengah; diterima karena
 * data statistik.
 *
 * `$dikunjungiPada` diterima sebagai string ISO-8601 (lewat defer(), tidak bisa membawa objek Carbon melewati
 * serialisasi closure). Dipakai dua cara: lewat Eloquent create() (casting 'datetime' menangani ISO-8601 dengan
 * offset zona waktu), dan lewat Builder::update()/increment() mentah pada `$kolom` di bawah — jalur kedua TIDAK
 * melalui casting Eloquent, sehingga diformat manual ke 'Y-m-d H:i:s' (MariaDB menolak offset '+07:00').
 */
class CatatKunjungan
{
    /**
     * @param  string  $dikunjungiPada  ISO-8601 (waktu klik di controller)
     * @param  bool  $catatDetail  false bila catat_kunjungan = false (hanya penghitung klik)
     * @param  bool  $sudahDikonsumsi  true bila jumlah_klik sudah dinaikkan atomik (sekali pakai/batas klik)
     */
    public function jalankan(
        string $tautanId,
        string $dikunjungiPada,
        string $ipAnonim,
        string $ipHash,
        ?string $userAgent,
        ?string $perujukHost,
        bool $botCepat,
        bool $catatDetail = true,
        bool $sudahDikonsumsi = false,
    ): void {
        $info = $this->urai($userAgent);
        $bot = $botCepat || $info['bot'];

        if ($catatDetail) {
            KunjunganTautan::create([
                'tautan_pendek_id' => $tautanId,
                'dikunjungi_pada' => $dikunjungiPada,
                'ip_anonim' => $ipAnonim,
                'ip_hash' => $ipHash,
                'peramban' => $info['peramban'],
                'versi_peramban' => $info['versi_peramban'],
                'os' => $info['os'],
                'versi_os' => $info['versi_os'],
                'jenis_perangkat' => $bot ? JenisPerangkat::Bot : $info['jenis'],
                'perujuk_host' => $perujukHost,
                'bot' => $bot,
                'nama_bot' => $bot ? ($info['nama_bot'] ?? ($botCepat ? 'Terdeteksi dari user agent' : null)) : null,
            ]);
        }

        if ($bot) {
            return;
        }

        $kolom = ['klik_terakhir_pada' => Carbon::parse($dikunjungiPada)->format('Y-m-d H:i:s')];
        $query = TautanPendek::withTrashed()->whereKey($tautanId);

        // Penghitung jumlah_klik: hanya manusia; tautan terbatas sudah dihitung atomik di controller (BR-13).
        $sudahDikonsumsi ? $query->update($kolom) : $query->increment('jumlah_klik', 1, $kolom);
    }

    /** @return array{bot: bool, nama_bot: ?string, peramban: ?string, versi_peramban: ?string, os: ?string, versi_os: ?string, jenis: JenisPerangkat} */
    private function urai(?string $userAgent): array
    {
        $kosong = ['bot' => false, 'nama_bot' => null, 'peramban' => null, 'versi_peramban' => null, 'os' => null, 'versi_os' => null, 'jenis' => JenisPerangkat::Lainnya];

        if ($userAgent === null || $userAgent === '') {
            return $kosong;
        }

        $detektor = new DeviceDetector(mb_substr($userAgent, 0, 512), ClientHints::factory([]));
        $detektor->skipBotDetection(false);
        $detektor->parse();

        if ($detektor->isBot()) {
            $bot = $detektor->getBot();

            return [...$kosong, 'bot' => true, 'nama_bot' => is_array($bot) ? mb_substr((string) ($bot['name'] ?? ''), 0, 100) : null, 'jenis' => JenisPerangkat::Bot];
        }

        return [
            'bot' => false,
            'nama_bot' => null,
            'peramban' => $this->teks($detektor->getClient('name'), 50),
            'versi_peramban' => $this->versiMayor($detektor->getClient('version')),
            'os' => $this->teks($detektor->getOs('name'), 50),
            'versi_os' => $this->versiMayor($detektor->getOs('version')),
            'jenis' => match (true) {
                $detektor->isTablet() => JenisPerangkat::Tablet,
                $detektor->isSmartphone() || $detektor->isFeaturePhone() || $detektor->isPhablet() => JenisPerangkat::Ponsel,
                $detektor->isDesktop() => JenisPerangkat::Desktop,
                default => JenisPerangkat::Lainnya,
            },
        ];
    }

    private function teks(mixed $nilai, int $maks): ?string
    {
        return is_string($nilai) && $nilai !== '' && $nilai !== DeviceDetector::UNKNOWN ? mb_substr($nilai, 0, $maks) : null;
    }

    private function versiMayor(mixed $versi): ?string
    {
        if (! is_string($versi) || $versi === '' || $versi === DeviceDetector::UNKNOWN) {
            return null;
        }

        return mb_substr(explode('.', $versi)[0], 0, 10);
    }
}
