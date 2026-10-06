<?php

namespace App\Models;

use App\Enums\CaraCocok;
use App\Enums\JenisSlugTerlarang;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property JenisSlugTerlarang $jenis
 * @property CaraCocok $cara_cocok
 * @property string $pola
 */
class SlugTerlarang extends Model
{
    use HasUuids, TercatatAktivitas;

    public const KUNCI_CACHE = 'alias:slug-terlarang';

    protected $table = 'slug_terlarang';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $attributes = ['aktif' => true, 'cara_cocok' => 'persis'];

    protected $fillable = ['pola', 'jenis', 'cara_cocok', 'keterangan', 'aktif', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisSlugTerlarang::class,
            'cara_cocok' => CaraCocok::class,
            'aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $m) => $m->pola = mb_strtolower(trim($m->pola)));
        static::saved(fn () => Cache::forget(self::KUNCI_CACHE));
        static::deleted(fn () => Cache::forget(self::KUNCI_CACHE));
    }

    protected function namaLog(): string
    {
        return 'moderasi';
    }

    /**
     * Daftar aktif (ber-cache) sebagai pasangan [pola, cara_cocok].
     *
     * @return list<array{pola: string, cara: string}>
     */
    public static function daftarAktif(): array
    {
        return Cache::remember(self::KUNCI_CACHE, 3600, fn (): array => self::query()
            ->where('aktif', true)
            ->get(['pola', 'cara_cocok'])
            ->map(fn (self $m) => ['pola' => $m->pola, 'cara' => $m->cara_cocok->value])
            ->all());
    }

    public static function cocokDengan(string $slug): ?string
    {
        foreach (self::daftarAktif() as $entri) {
            if (CaraCocok::from($entri['cara'])->cocok($slug, $entri['pola'])) {
                return $entri['pola'];
            }
        }

        return null;
    }
}
