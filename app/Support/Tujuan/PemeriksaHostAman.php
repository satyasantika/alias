<?php

namespace App\Support\Tujuan;

use App\Exceptions\UrlTujuanTidakValid;

/** BR-06: anti-SSRF — tolak IP privat/reserved, nama internal, dan host yang me-resolve ke IP privat. */
class PemeriksaHostAman
{
    /** @var list<string> */
    private const CIDR_V4 = [
        '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24',
        '192.0.2.0/24', '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24',
        '224.0.0.0/4', '240.0.0.0/4',
    ];

    /** @var list<string> */
    private const CIDR_V6 = ['::/128', '::1/128', '64:ff9b::/96', '100::/64', '2001:db8::/32', 'fc00::/7', 'fe80::/10', 'ff00::/8'];

    public function __construct(private readonly ResolverDns $dns) {}

    /**
     * @return list<string> peringatan (mis. DNS gagal) — tidak memblokir
     *
     * @throws UrlTujuanTidakValid
     */
    public function periksa(string $host): array
    {
        if (str_contains($host, ':') || filter_var($host, FILTER_VALIDATE_IP)) {
            if (! $this->ipAman($host)) {
                throw new UrlTujuanTidakValid('URL tujuan tidak boleh mengarah ke alamat IP privat atau internal.');
            }

            return [];
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new UrlTujuanTidakValid('URL tujuan tidak boleh mengarah ke host lokal atau internal.');
        }

        $label = explode('.', $host);
        $terakhir = end($label);

        // 2130706433, 0x7f.1, 0177.0.0.1 diperlakukan sebagai IP oleh banyak klien.
        if (ctype_digit($terakhir) || str_starts_with($terakhir, '0x') || count($label) === 1) {
            throw new UrlTujuanTidakValid(count($label) === 1 && ! ctype_digit($terakhir) && ! str_starts_with($terakhir, '0x')
                ? 'URL tujuan harus memakai nama domain lengkap (bukan nama host internal).'
                : 'URL tujuan tidak boleh memakai alamat IP dalam format tidak standar.');
        }

        $ip = $this->dns->resolve($host);

        if ($ip === []) {
            return ['Alamat domain tujuan belum dapat di-resolve saat ini; pastikan tautan benar.'];
        }

        if (! $this->hostInternalDiizinkan($host)) {
            foreach ($ip as $alamat) {
                if (! $this->ipAman($alamat)) {
                    throw new UrlTujuanTidakValid('Domain tujuan mengarah ke alamat jaringan internal sehingga tidak diizinkan.');
                }
            }
        }

        return [];
    }

    public function hostInternalDiizinkan(string $host): bool
    {
        foreach (config('alias.domain_internal_diizinkan') as $pola) {
            if (PencocokAturanDomain::cocokPola($host, $pola)) {
                return true;
            }
        }

        return false;
    }

    public function ipAman(string $ip): bool
    {
        $ip = strtolower(trim($ip, '[]'));
        $biner = @inet_pton($ip);
        if ($biner === false) {
            return false;
        }

        if (strlen($biner) === 16) {
            // IPv4-mapped (::ffff:a.b.c.d) → periksa sebagai IPv4.
            if (str_starts_with($biner, str_repeat("\0", 10)."\xff\xff")) {
                return $this->ipAman(inet_ntop(substr($biner, 12)));
            }

            return ! $this->dalamDaftar($biner, self::CIDR_V6);
        }

        return ! $this->dalamDaftar($biner, self::CIDR_V4);
    }

    /** @param  list<string>  $daftar */
    private function dalamDaftar(string $biner, array $daftar): bool
    {
        foreach ($daftar as $cidr) {
            [$jaringan, $prefiks] = explode('/', $cidr);
            $biarJaringan = inet_pton($jaringan);
            $bit = (int) $prefiks;
            $byte = intdiv($bit, 8);
            $sisa = $bit % 8;

            if (substr($biner, 0, $byte) !== substr($biarJaringan, 0, $byte)) {
                continue;
            }

            if ($sisa === 0) {
                return true;
            }

            $mask = (0xFF << (8 - $sisa)) & 0xFF;
            if ((ord($biner[$byte]) & $mask) === (ord($biarJaringan[$byte]) & $mask)) {
                return true;
            }
        }

        return false;
    }
}
