<?php

namespace App\Support\Kunjungan;

/** BR-17: hanya host perujuk (tanpa path/query). */
class HostPerujuk
{
    public static function dari(?string $perujuk): ?string
    {
        if ($perujuk === null || $perujuk === '' || strlen($perujuk) > 2048) {
            return null;
        }

        $bagian = parse_url($perujuk);
        $host = strtolower((string) ($bagian['host'] ?? ''));

        if ($host === '' || ! in_array(strtolower((string) ($bagian['scheme'] ?? '')), ['http', 'https'], true)) {
            return null;
        }

        return mb_substr($host, 0, 255);
    }
}
