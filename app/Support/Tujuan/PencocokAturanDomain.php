<?php

namespace App\Support\Tujuan;

use App\Models\AturanDomain;
use App\Support\Pengaturan;

/** BR-07: aturan blokir selalu didahulukan; mode daftar_putih mewajibkan kecocokan aturan izinkan. */
class PencocokAturanDomain
{
    /** `*.contoh.com` cocok dengan contoh.com dan semua subdomainnya; selain itu persis. */
    public static function cocokPola(string $host, string $pola): bool
    {
        $host = strtolower($host);
        $pola = strtolower($pola);

        if (str_starts_with($pola, '*.')) {
            $dasar = substr($pola, 2);

            return $host === $dasar || str_ends_with($host, '.'.$dasar);
        }

        return $host === $pola;
    }

    public function diblokir(string $host): bool
    {
        foreach (AturanDomain::daftarAktif()['blokir'] as $pola) {
            if (self::cocokPola($host, $pola)) {
                return true;
            }
        }

        return $this->domainSendiri($host);
    }

    public function diizinkan(string $host): bool
    {
        foreach (AturanDomain::daftarAktif()['izinkan'] as $pola) {
            if (self::cocokPola($host, $pola)) {
                return true;
            }
        }

        return false;
    }

    public function modeDaftarPutih(): bool
    {
        return Pengaturan::ambil('mode_domain', 'bebas') === 'daftar_putih';
    }

    /** BR-07: domain pendek, domain panel, dan host APP_URL milik Alias sendiri. */
    public function domainSendiri(string $host): bool
    {
        $milik = array_filter([
            config('alias.domain_pendek'),
            config('alias.domain_panel'),
            parse_url((string) config('app.url'), PHP_URL_HOST) ?: null,
        ]);

        foreach ($milik as $domain) {
            if (strtolower($host) === strtolower((string) $domain)) {
                return true;
            }
        }

        return false;
    }
}
