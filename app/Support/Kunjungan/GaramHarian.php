<?php

namespace App\Support\Kunjungan;

use Illuminate\Support\Facades\Cache;

/**
 * BR-16: garam harian acak (32 byte) di cache store, TTL 48 jam, tidak pernah ditulis ke DB/log.
 * Hash IP berbeda tiap hari sehingga tidak dapat dikaitkan lintas hari.
 */
class GaramHarian
{
    public static function kunci(?string $tanggal = null): string
    {
        return 'alias:garam-ip:'.($tanggal ?? now()->format('Y-m-d'));
    }

    public static function untukHariIni(): string
    {
        $kunci = self::kunci();
        Cache::add($kunci, bin2hex(random_bytes(32)), now()->addHours(48));

        return (string) Cache::get($kunci);
    }

    /** HMAC-SHA256(IP utuh, garam harian) → 64 karakter heksa. */
    public static function hashIp(string $ip): string
    {
        return hash_hmac('sha256', $ip, self::untukHariIni());
    }
}
