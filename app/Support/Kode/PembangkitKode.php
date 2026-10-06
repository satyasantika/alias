<?php

namespace App\Support\Kode;

use App\Exceptions\KodeGagalDibangkitkan;
use App\Models\SlugTerlarang;
use App\Models\TautanPendek;
use Illuminate\Support\Str;

/** BR-01: kode acak base62, 5 percobaan lalu panjang+1 untuk 5 percobaan berikutnya. */
class PembangkitKode
{
    public const PERCOBAAN = 5;

    public function buat(): string
    {
        $panjang = (int) config('alias.panjang_kode');

        foreach ([$panjang, $panjang + 1] as $p) {
            for ($i = 0; $i < self::PERCOBAAN; $i++) {
                $kandidat = Str::random($p);

                if ($this->layak($kandidat)) {
                    return $kandidat;
                }
            }
        }

        throw new KodeGagalDibangkitkan('Tidak berhasil membangkitkan kode unik; coba lagi.');
    }

    public function layak(string $kode): bool
    {
        return SlugTerlarang::cocokDengan($kode) === null
            && ! DaftarSegmenRute::sistem($kode)
            && ! TautanPendek::withTrashed()->where('kode', $kode)->exists();
    }
}
