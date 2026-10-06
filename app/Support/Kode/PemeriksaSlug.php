<?php

namespace App\Support\Kode;

use App\Models\SlugTerlarang;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Str;

/** BR-02 dan BR-03. Mengembalikan pesan galat Indonesia, atau null bila slug sah. */
class PemeriksaSlug
{
    public const POLA = '/^[a-z0-9](?:[a-z0-9]|-(?=[a-z0-9])){2,49}$/';

    public static function normalisasi(string $slug): string
    {
        return Str::lower(trim($slug));
    }

    public function periksa(string $slug, ?User $pembuat = null, ?Unit $untukUnit = null, ?string $kecualiId = null): ?string
    {
        $slug = self::normalisasi($slug);

        if (! preg_match(self::POLA, $slug)) {
            return 'Slug harus 3–50 karakter: huruf kecil, angka, dan strip tunggal (tidak diawali atau diakhiri strip).';
        }

        if (SlugTerlarang::cocokDengan($slug, abaikanPrefiksUnit: true) !== null) {
            return 'Slug ini dicadangkan sistem atau tidak diperbolehkan.';
        }

        if (DaftarSegmenRute::sistem($slug)) {
            return 'Slug ini dicadangkan sistem.';
        }

        if (config('alias.namespace_unit') && ($pesan = $this->periksaNamespaceUnit($slug, $pembuat, $untukUnit)) !== null) {
            return $pesan;
        }

        $dipakai = TautanPendek::withTrashed()->where('kode', $slug)
            ->when($kecualiId, fn ($q) => $q->whereKeyNot($kecualiId))
            ->exists();

        return $dipakai ? 'Slug sudah dipakai.' : null;
    }

    /** BR-22: prefiks unit hanya boleh dipakai anggota/untuk unit tersebut. */
    private function periksaNamespaceUnit(string $slug, ?User $pembuat, ?Unit $untukUnit): ?string
    {
        $unit = Unit::query()->whereNotNull('prefiks_slug')->get(['id', 'prefiks_slug'])
            ->first(fn (Unit $u) => str_starts_with($slug, $u->prefiks_slug.'-'));

        if ($unit === null) {
            return null;
        }

        $sah = ($untukUnit?->is($unit) ?? false) || ($pembuat?->anggotaUnit($unit) ?? false);

        return $sah ? null : 'Awalan slug ini dicadangkan untuk unit lain.';
    }
}
