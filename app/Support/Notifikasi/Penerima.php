<?php

namespace App\Support\Notifikasi;

use App\Enums\JenisKepemilikan;
use App\Enums\PeranUnit;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Collection;

/** Penentuan penerima notifikasi: hanya pengguna aktif, tanpa duplikat. */
class Penerima
{
    /**
     * Pemilik pribadi, atau semua pengelola unit bila tautan milik unit.
     *
     * @return Collection<int, User>
     */
    public static function pemilikTautan(TautanPendek $tautan): Collection
    {
        if ($tautan->jenis_kepemilikan === JenisKepemilikan::Pribadi) {
            return self::aktif(User::query()->whereKey($tautan->pemilik_id)->get());
        }

        return self::pengelolaUnit($tautan->unit_id);
    }

    /** @return Collection<int, User> */
    public static function pengelolaUnit(?string $unitId): Collection
    {
        if ($unitId === null) {
            return collect();
        }

        $unit = Unit::query()->find($unitId);

        return $unit === null ? collect() : self::aktif($unit->anggota()->wherePivot('peran_unit', PeranUnit::Pengelola->value)->get());
    }

    /**
     * Pembuat tautan (mis. anggota unit yang mengajukan slug).
     *
     * @return Collection<int, User>
     */
    public static function pembuat(TautanPendek $tautan): Collection
    {
        return self::aktif(User::query()->whereKey($tautan->dibuat_oleh)->get());
    }

    /**
     * Admin pemegang permission tertentu.
     *
     * @return Collection<int, User>
     */
    public static function pemegangIzin(string $izin): Collection
    {
        // whereHas (bukan scope ::permission) agar tidak melempar bila permission belum disemai.
        return self::aktif(User::query()
            ->where(fn ($q) => $q
                ->whereHas('roles.permissions', fn ($p) => $p->where('name', $izin))
                ->orWhereHas('permissions', fn ($p) => $p->where('name', $izin)))
            ->get());
    }

    /**
     * @param  Collection<int, User>  ...$kumpulan
     * @return Collection<int, User>
     */
    public static function gabung(Collection ...$kumpulan): Collection
    {
        return self::aktif(collect($kumpulan)->flatten(1));
    }

    /**
     * @param  Collection<int, User>  $pengguna
     * @return Collection<int, User>
     */
    private static function aktif(Collection $pengguna): Collection
    {
        return $pengguna->filter(fn (User $u) => $u->aktif && ! $u->trashed())->unique(fn (User $u) => $u->getKey())->values();
    }
}
