<?php

namespace App\Support\Kunjungan;

/** BR-16: IPv4 → x.y.z.0, IPv6 → /48. IP utuh tidak pernah disimpan. */
class AnonimisasiIp
{
    public static function anonimkan(string $ip): string
    {
        $biner = @inet_pton($ip);

        if ($biner === false) {
            return '0.0.0.0';
        }

        if (strlen($biner) === 4) {
            return substr($biner, 0, 3) === '' ? '0.0.0.0' : inet_ntop(substr($biner, 0, 3)."\0");
        }

        // IPv4-mapped (::ffff:a.b.c.d) diperlakukan sebagai IPv4.
        if (str_starts_with($biner, str_repeat("\0", 10)."\xff\xff")) {
            return inet_ntop(substr($biner, 12, 3)."\0");
        }

        return inet_ntop(substr($biner, 0, 6).str_repeat("\0", 10));
    }
}
