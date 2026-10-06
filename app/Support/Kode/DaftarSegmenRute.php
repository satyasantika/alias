<?php

namespace App\Support\Kode;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

/** BR-03(b), BR-36: segmen pertama semua rute terdaftar + config('alias.segmen_sistem'). */
class DaftarSegmenRute
{
    public const KUNCI_CACHE = 'alias:segmen-rute';

    /** @return list<string> huruf kecil, unik */
    public static function semua(): array
    {
        return Cache::remember(self::KUNCI_CACHE, 3600, fn (): array => self::hitung());
    }

    /** @return list<string> */
    public static function hitung(): array
    {
        $segmen = array_map('strtolower', config('alias.segmen_sistem'));

        foreach (Route::getRoutes()->getRoutes() as $rute) {
            $pertama = explode('/', trim($rute->uri(), '/'))[0];
            if ($pertama !== '' && ! str_starts_with($pertama, '{')) {
                $segmen[] = strtolower($pertama);
            }
        }

        return array_values(array_unique($segmen));
    }

    public static function sistem(string $kode): bool
    {
        return in_array(strtolower($kode), self::semua(), true);
    }

    /**
     * Pola `where` untuk /{kode}: alfabet kode + negative lookahead yang hanya menolak segmen sistem PERSIS
     * (mis. `panel`, `panel+`), bukan slug yang sekadar berawalan sama (`api-docs` tetap cocok). (02 §5.2)
     */
    public static function polaKode(): string
    {
        $segmen = array_map(fn (string $s) => preg_quote($s, '#'), self::hitung());

        return '(?!(?:'.implode('|', $segmen).')\+?$)[A-Za-z0-9][A-Za-z0-9-]{1,63}';
    }

    public static function lupakan(): void
    {
        Cache::forget(self::KUNCI_CACHE);
    }
}
