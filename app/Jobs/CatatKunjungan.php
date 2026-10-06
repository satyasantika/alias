<?php

namespace App\Jobs;

use App\Enums\JenisPerangkat;
use App\Models\KunjunganTautan;
use App\Models\TautanPendek;
use DeviceDetector\ClientHints;
use DeviceDetector\DeviceDetector;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mencatat kunjungan anonim (BR-16/17). Payload TIDAK berisi IP utuh; user agent hanya sementara di payload
 * untuk diurai lalu dibuang (tidak disimpan).
 */
class CatatKunjungan implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  string  $dikunjungiPada  ISO-8601 (waktu klik di controller)
     * @param  bool  $catatDetail  false bila catat_kunjungan = false (hanya penghitung klik)
     * @param  bool  $sudahDikonsumsi  true bila jumlah_klik sudah dinaikkan atomik (sekali pakai/batas klik)
     */
    public function __construct(
        public readonly string $tautanId,
        public readonly string $dikunjungiPada,
        public readonly string $ipAnonim,
        public readonly string $ipHash,
        public readonly ?string $userAgent,
        public readonly ?string $perujukHost,
        public readonly bool $botCepat,
        public readonly bool $catatDetail = true,
        public readonly bool $sudahDikonsumsi = false,
    ) {
        $this->onQueue('kunjungan');
    }

    public function handle(): void
    {
        $info = $this->urai();
        $bot = $this->botCepat || $info['bot'];

        if ($this->catatDetail) {
            KunjunganTautan::create([
                'tautan_pendek_id' => $this->tautanId,
                'dikunjungi_pada' => $this->dikunjungiPada,
                'ip_anonim' => $this->ipAnonim,
                'ip_hash' => $this->ipHash,
                'peramban' => $info['peramban'],
                'versi_peramban' => $info['versi_peramban'],
                'os' => $info['os'],
                'versi_os' => $info['versi_os'],
                'jenis_perangkat' => $bot ? JenisPerangkat::Bot : $info['jenis'],
                'perujuk_host' => $this->perujukHost,
                'bot' => $bot,
                'nama_bot' => $bot ? ($info['nama_bot'] ?? ($this->botCepat ? 'Terdeteksi dari user agent' : null)) : null,
            ]);
        }

        if ($bot) {
            return;
        }

        $kolom = ['klik_terakhir_pada' => $this->dikunjungiPada];
        $query = TautanPendek::withTrashed()->whereKey($this->tautanId);

        // Penghitung jumlah_klik: hanya manusia; tautan terbatas sudah dihitung atomik di controller (BR-13).
        $this->sudahDikonsumsi ? $query->update($kolom) : $query->increment('jumlah_klik', 1, $kolom);
    }

    /** @return array{bot: bool, nama_bot: ?string, peramban: ?string, versi_peramban: ?string, os: ?string, versi_os: ?string, jenis: JenisPerangkat} */
    private function urai(): array
    {
        $kosong = ['bot' => false, 'nama_bot' => null, 'peramban' => null, 'versi_peramban' => null, 'os' => null, 'versi_os' => null, 'jenis' => JenisPerangkat::Lainnya];

        if ($this->userAgent === null || $this->userAgent === '') {
            return $kosong;
        }

        $detektor = new DeviceDetector(mb_substr($this->userAgent, 0, 512), ClientHints::factory([]));
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
