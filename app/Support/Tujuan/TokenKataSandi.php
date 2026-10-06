<?php

namespace App\Support\Tujuan;

/**
 * Token tanpa sesi untuk formulir kata sandi tautan (BR-35): HMAC(kode, jendela 10 menit, APP_KEY).
 * Halaman pengalihan sengaja tanpa sesi/cookie, sehingga CSRF diganti token terikat-kode berumur pendek.
 */
class TokenKataSandi
{
    public const JENDELA_DETIK = 600;

    public static function buat(string $kode, ?int $waktu = null): string
    {
        $jendela = intdiv($waktu ?? time(), self::JENDELA_DETIK);

        return $jendela.'.'.self::tanda($kode, $jendela);
    }

    public static function sah(string $kode, string $token, ?int $waktu = null): bool
    {
        [$jendela, $tanda] = array_pad(explode('.', $token, 2), 2, '');

        if (! ctype_digit($jendela)) {
            return false;
        }

        $sekarang = intdiv($waktu ?? time(), self::JENDELA_DETIK);
        $umur = $sekarang - (int) $jendela;

        // Berlaku dalam jendela yang sama atau satu jendela sebelumnya (maks ~20 menit).
        return $umur >= 0 && $umur <= 1 && hash_equals(self::tanda($kode, (int) $jendela), $tanda);
    }

    private static function tanda(string $kode, int $jendela): string
    {
        return hash_hmac('sha256', "kata-sandi-tautan|{$kode}|{$jendela}", (string) config('app.key'));
    }
}
