<?php

namespace App\Support\Tujuan;

use App\Enums\StatusCekTujuan;
use App\Exceptions\HostSibuk;
use App\Exceptions\UrlTujuanTidakValid;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * BR-29: pemeriksaan kesehatan tujuan yang aman.
 * - HEAD, lalu GET bila 405/501; timeout 10 dtk; redirect diikuti manual maks 5 hop.
 * - Setiap hop divalidasi (BR-05–08) dan IP hasil resolusi diperiksa SEBELUM koneksi; koneksi dipaku ke IP itu
 *   (CURLOPT_RESOLVE) sehingga tidak ada celah DNS-rebinding.
 * - Badan respons tidak pernah dibaca (stream), sehingga > 64 KB tidak pernah diunduh.
 */
class PemeriksaTujuan
{
    public const MAKS_REDIRECT = 5;

    public const TIMEOUT = 10;

    public function __construct(
        private readonly ValidatorUrlTujuan $validator,
        private readonly PemeriksaHostAman $hostAman,
        private readonly ResolverDns $dns,
    ) {}

    public function periksa(string $url): HasilCek
    {
        for ($hop = 0; $hop <= self::MAKS_REDIRECT; $hop++) {
            try {
                $valid = $this->validator->validasi($url);
            } catch (UrlTujuanTidakValid $e) {
                return new HasilCek(null, 0, 'Ditolak validasi: '.$e->getMessage());
            }

            $ip = $this->pilihIp($valid['host']);
            if ($ip === null) {
                return new HasilCek(null, 0, 'DNS gagal atau mengarah ke jaringan internal');
            }

            $this->batasiLajuHost($valid['host']);

            try {
                $respons = $this->kirim($valid['url'], $valid['host'], $ip);
            } catch (HostSibuk $e) {
                throw $e;
            } catch (ConnectionException) {
                return new HasilCek(null, 0, 'Koneksi gagal atau timeout');
            } catch (Throwable $e) {
                return new HasilCek(null, 0, 'Galat: '.$e::class);
            }

            $kode = $respons['kode'];

            if (in_array($kode, [301, 302, 303, 307, 308], true) && $respons['lokasi'] !== null) {
                $url = $this->selesaikanLokasi($valid['url'], $respons['lokasi']);

                continue;
            }

            return $this->klasifikasi($kode);
        }

        return new HasilCek(null, 0, 'Terlalu banyak pengalihan (maks '.self::MAKS_REDIRECT.')');
    }

    private function klasifikasi(int $kode): HasilCek
    {
        return match (true) {
            $kode >= 200 && $kode < 400 => new HasilCek(StatusCekTujuan::Sehat, $kode),
            $kode === 401 || $kode === 403 => new HasilCek(StatusCekTujuan::Terbatas, $kode),
            default => new HasilCek(null, $kode, "Respons {$kode}"),
        };
    }

    /** IP pertama yang aman (atau IP literal itu sendiri); null bila tidak ada. */
    private function pilihIp(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP) || str_contains($host, ':')) {
            return $this->hostAman->ipAman($host) ? trim($host, '[]') : null;
        }

        $internalDiizinkan = $this->hostAman->hostInternalDiizinkan($host);

        foreach ($this->dns->resolve($host) as $ip) {
            if ($internalDiizinkan || $this->hostAman->ipAman($ip)) {
                return $ip;
            }
        }

        return null;
    }

    /** Maks 2 permintaan/detik per host. */
    private function batasiLajuHost(string $host): void
    {
        $kunci = 'cek-host:'.$host;

        for ($i = 0; $i < 6; $i++) {
            if (RateLimiter::attempt($kunci, 2, fn () => true, 1)) {
                return;
            }
            usleep(200_000);
        }

        throw new HostSibuk("Host {$host} sedang dibatasi");
    }

    /** @return array{kode: int, lokasi: ?string} */
    private function kirim(string $url, string $host, string $ip): array
    {
        $klien = $this->klien($url, $host, $ip);

        $respons = $klien->send('HEAD', $url);
        if (in_array($respons->status(), [405, 501], true)) {
            $respons = $this->klien($url, $host, $ip)->send('GET', $url);
        }

        $hasil = ['kode' => $respons->status(), 'lokasi' => $respons->header('Location') ?: null];
        $respons->toPsrResponse()->getBody()->close();

        return $hasil;
    }

    private function klien(string $url, string $host, string $ip): PendingRequest
    {
        $port = parse_url($url, PHP_URL_PORT) ?: (str_starts_with($url, 'https') ? 443 : 80);
        $opsi = [
            'allow_redirects' => false,
            'stream' => true,
            'http_errors' => false,
        ];

        if (! filter_var($host, FILTER_VALIDATE_IP) && defined('CURLOPT_RESOLVE')) {
            $opsi['curl'] = [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]];
        }

        return Http::withOptions($opsi)
            ->timeout(self::TIMEOUT)
            ->connectTimeout(5)
            ->withUserAgent('AliasFKIP-LinkCheck/1.0 (+kontak admin Alias FKIP)')
            ->retry(0);
    }

    private function selesaikanLokasi(string $dasar, string $lokasi): string
    {
        if (preg_match('#^https?://#i', $lokasi)) {
            return $lokasi;
        }

        $bagian = parse_url($dasar);
        $skema = $bagian['scheme'] ?? 'https';
        $host = $bagian['host'] ?? '';
        $port = isset($bagian['port']) ? ':'.$bagian['port'] : '';

        if (str_starts_with($lokasi, '//')) {
            return $skema.':'.$lokasi;
        }

        if (str_starts_with($lokasi, '/')) {
            return "{$skema}://{$host}{$port}{$lokasi}";
        }

        $dir = rtrim(dirname($bagian['path'] ?? '/'), '/');

        return "{$skema}://{$host}{$port}{$dir}/{$lokasi}";
    }
}
