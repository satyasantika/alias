<?php

namespace App\Support\Tujuan;

use App\Exceptions\UrlTujuanTidakValid;

/** BR-05: normalisasi dan validasi sintaks URL tujuan. */
class NormalisasiUrl
{
    public const PANJANG_MAKS = 2048;

    /** @return array{url: string, host: string} */
    public function normalisasi(string $masukan): array
    {
        $url = trim($masukan);

        if ($url === '') {
            throw new UrlTujuanTidakValid('URL tujuan wajib diisi.');
        }

        if (strlen($url) > self::PANJANG_MAKS) {
            throw new UrlTujuanTidakValid('URL tujuan terlalu panjang (maksimal '.self::PANJANG_MAKS.' karakter).');
        }

        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new UrlTujuanTidakValid('URL tujuan tidak boleh mengandung spasi, karakter kontrol, atau garis miring terbalik.');
        }

        if (str_starts_with($url, '//')) {
            throw new UrlTujuanTidakValid('URL tujuan harus lengkap dengan http:// atau https://.');
        }

        $bagian = parse_url($url);
        if ($bagian === false || ! isset($bagian['scheme'])) {
            throw new UrlTujuanTidakValid('URL tujuan harus lengkap dengan http:// atau https://.');
        }

        $skema = strtolower($bagian['scheme']);
        if (! in_array($skema, ['http', 'https'], true)) {
            throw new UrlTujuanTidakValid('URL tujuan hanya boleh memakai http atau https.');
        }

        if (isset($bagian['user']) || isset($bagian['pass'])) {
            throw new UrlTujuanTidakValid('URL tujuan tidak boleh memuat nama pengguna atau kata sandi.');
        }

        $host = $bagian['host'] ?? '';
        if ($host === '') {
            throw new UrlTujuanTidakValid('URL tujuan tidak memiliki host yang valid.');
        }

        $host = $this->normalisasiHost($host);

        $hasil = $skema.'://'.(str_contains($host, ':') ? "[{$host}]" : $host);
        if (isset($bagian['port'])) {
            $hasil .= ':'.$bagian['port'];
        }
        $hasil .= $this->enkodeNonAscii($bagian['path'] ?? '');
        if (isset($bagian['query'])) {
            $hasil .= '?'.$this->enkodeNonAscii($bagian['query']);
        }
        if (isset($bagian['fragment'])) {
            $hasil .= '#'.$this->enkodeNonAscii($bagian['fragment']);
        }

        if (strlen($hasil) > self::PANJANG_MAKS) {
            throw new UrlTujuanTidakValid('URL tujuan terlalu panjang (maksimal '.self::PANJANG_MAKS.' karakter).');
        }

        return ['url' => $hasil, 'host' => $host];
    }

    private function normalisasiHost(string $host): string
    {
        $host = strtolower(trim($host, '[]'));
        $host = rtrim($host, '.');

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $host;
        }

        if (preg_match('/[^\x00-\x7f]/', $host)) {
            $ascii = idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) {
                throw new UrlTujuanTidakValid('Nama host pada URL tujuan tidak valid.');
            }
            $host = strtolower($ascii);
        }

        if (! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)*$/', $host) || strlen($host) > 253) {
            throw new UrlTujuanTidakValid('Nama host pada URL tujuan tidak valid.');
        }

        return $host;
    }

    private function enkodeNonAscii(string $bagian): string
    {
        return (string) preg_replace_callback('/[^\x21-\x7e]/', fn (array $m) => rawurlencode($m[0]), $bagian);
    }
}
