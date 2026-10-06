<?php

namespace App\Support\Tujuan;

/** BR-15: tambahkan query permintaan ke URL tujuan tanpa menimpa parameter yang ada; fragmen dipertahankan. */
class TeruskanQuery
{
    /** @param  array<string, mixed>  $queryPermintaan */
    public static function gabungkan(string $tujuan, array $queryPermintaan): string
    {
        if ($queryPermintaan === []) {
            return $tujuan;
        }

        $fragmen = '';
        if (($posisi = strpos($tujuan, '#')) !== false) {
            $fragmen = substr($tujuan, $posisi);
            $tujuan = substr($tujuan, 0, $posisi);
        }

        $query = '';
        if (($posisi = strpos($tujuan, '?')) !== false) {
            $query = substr($tujuan, $posisi + 1);
            $tujuan = substr($tujuan, 0, $posisi);
        }

        parse_str($query, $sudahAda);
        $tambahan = array_diff_key($queryPermintaan, $sudahAda);

        if ($tambahan === []) {
            return $tujuan.($query !== '' ? '?'.$query : '').$fragmen;
        }

        $gabung = $query !== '' ? $query.'&'.http_build_query($tambahan, '', '&', PHP_QUERY_RFC3986) : http_build_query($tambahan, '', '&', PHP_QUERY_RFC3986);
        $hasil = $tujuan.'?'.$gabung.$fragmen;

        // Terlalu panjang → abaikan query permintaan.
        return strlen($hasil) > NormalisasiUrl::PANJANG_MAKS
            ? $tujuan.($query !== '' ? '?'.$query : '').$fragmen
            : $hasil;
    }
}
