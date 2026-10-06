<?php

namespace App\Support\Tujuan;

use App\Exceptions\UrlTujuanTidakValid;
use App\Models\User;

/** BR-05 s.d. BR-08 digabung. */
class ValidatorUrlTujuan
{
    public function __construct(
        private readonly NormalisasiUrl $normalisasi,
        private readonly PemeriksaHostAman $hostAman,
        private readonly PencocokAturanDomain $aturan,
    ) {}

    /**
     * @return array{url: string, host: string, hash: string, peringatan: list<string>}
     *
     * @throws UrlTujuanTidakValid
     */
    public function validasi(string $masukan, ?User $oleh = null): array
    {
        ['url' => $url, 'host' => $host] = $this->normalisasi->normalisasi($masukan);

        if ($this->aturan->domainSendiri($host)) {
            throw new UrlTujuanTidakValid('URL tujuan tidak boleh mengarah ke Alias FKIP sendiri.');
        }

        $peringatan = $this->hostAman->periksa($host);

        if ($this->aturan->diblokir($host)) {
            throw new UrlTujuanTidakValid('Domain tujuan diblokir (pemendek pihak ketiga atau domain terlarang).');
        }

        if ($this->aturan->modeDaftarPutih() && ! $this->aturan->diizinkan($host)) {
            throw new UrlTujuanTidakValid('Domain tujuan tidak ada dalam daftar domain yang diizinkan.');
        }

        $this->periksaKataKunci($url, $host, $oleh);

        return ['url' => $url, 'host' => $host, 'hash' => hash('sha256', $url), 'peringatan' => $peringatan];
    }

    private function periksaKataKunci(string $url, string $host, ?User $oleh): void
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        foreach (config('alias.kata_kunci_tujuan_terlarang') as $kata) {
            if (str_contains($host, $kata) || str_contains($path, $kata)) {
                // BR-08: dicatat sebagai percobaan mencurigakan (hanya host & kata kunci, tanpa URL lengkap).
                activity('moderasi')
                    ->causedBy($oleh)
                    ->event('percobaan-mencurigakan')
                    ->withProperties(['host' => $host, 'kata_kunci' => $kata])
                    ->log('Percobaan membuat tautan ke tujuan yang mengandung kata kunci terlarang');

                throw new UrlTujuanTidakValid('URL tujuan mengandung kata kunci yang tidak diperbolehkan.');
            }
        }
    }
}
