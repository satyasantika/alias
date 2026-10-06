<?php

namespace App\Support\Kode;

use App\Enums\CaraCocok;
use App\Enums\JenisSlugTerlarang;
use App\Models\SlugTerlarang;
use App\Models\Unit;

/**
 * BR-22: prefiks unit otomatis menjadi entri `slug_terlarang` jenis awalan agar non-anggota tidak dapat memakainya.
 * Aktif hanya bila config alias.namespace_unit = true. Pemeriksaan keanggotaan dilakukan PemeriksaSlug.
 */
class SinkronPrefiksUnit
{
    public static function sinkron(Unit $unit): void
    {
        $penanda = SlugTerlarang::PENANDA_PREFIKS_UNIT.$unit->getKey();
        $entri = SlugTerlarang::query()->where('keterangan', $penanda)->first();

        $aktif = config('alias.namespace_unit') && filled($unit->prefiks_slug) && $unit->aktif && ! $unit->trashed();

        if (! $aktif) {
            $entri?->delete();

            return;
        }

        $pola = mb_strtolower($unit->prefiks_slug).'-';

        if ($entri === null) {
            SlugTerlarang::create([
                'pola' => $pola, 'jenis' => JenisSlugTerlarang::CadanganKelembagaan, 'cara_cocok' => CaraCocok::Awalan,
                'keterangan' => $penanda,
            ]);

            return;
        }

        if ($entri->pola !== $pola) {
            $entri->update(['pola' => $pola]);
        }
    }

    /** Menyinkronkan seluruh unit (mis. setelah fitur diaktifkan). */
    public static function sinkronSemua(): int
    {
        $jumlah = 0;
        foreach (Unit::withTrashed()->get() as $unit) {
            self::sinkron($unit);
            $jumlah++;
        }

        return $jumlah;
    }
}
