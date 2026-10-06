<?php

namespace App\Support\Tujuan;

/** Pembungkus DNS agar dapat diganti dengan palsu pada uji. */
class ResolverDns
{
    /** @return list<string> alamat IP (A + AAAA); kosong bila gagal */
    public function resolve(string $host): array
    {
        $ip = [];
        foreach ([DNS_A => 'ip', DNS_AAAA => 'ipv6'] as $tipe => $kunci) {
            $rekaman = @dns_get_record($host, $tipe);
            foreach ($rekaman ?: [] as $r) {
                if (isset($r[$kunci])) {
                    $ip[] = $r[$kunci];
                }
            }
        }

        return $ip;
    }
}
