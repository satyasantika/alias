<?php

namespace App\Models;

use App\Enums\CaraCocok;
use App\Enums\JenisSlugTerlarang;
use App\Jobs\PindaiUlangAturan;
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

    /** Awalan keterangan untuk entri otomatis dari unit.prefiks_slug (BR-22). */
    public const PENANDA_PREFIKS_UNIT = 'prefiks-unit:';

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
        static::saved(function (self $m): void {
            Cache::forget(self::KUNCI_CACHE);

            if ($m->aktif && (($m->wasRecentlyCreated && $m->getChanges() === []) || $m->wasChanged(['pola', 'cara_cocok', 'aktif']))) {
                PindaiUlangAturan::kerjakan('slug', $m->getKey());
            }
        });
        static::deleted(fn () => Cache::forget(self::KUNCI_CACHE));
    }

    protected function namaLog(): string
    {
        return 'moderasi';
    }

    /**
     * Daftar aktif (ber-cache) sebagai pasangan [pola, cara_cocok].
     *
     * @return list<array{pola: string, cara: string, unit: bool}>
     */
    public static function daftarAktif(): array
    {
        return Cache::remember(self::KUNCI_CACHE, 3600, fn (): array => self::query()
            ->where('aktif', true)
            ->get(['pola', 'cara_cocok', 'keterangan'])
            ->map(fn (self $m) => [
                'pola' => $m->pola,
                'cara' => $m->cara_cocok->value,
                'unit' => str_starts_with((string) $m->keterangan, self::PENANDA_PREFIKS_UNIT),
            ])
            ->all());
    }

    /**
     * @param  bool  $abaikanPrefiksUnit  true: entri prefiks unit (BR-22) dilewati karena dinilai terpisah menurut keanggotaan
     */
    public static function cocokDengan(string $slug, bool $abaikanPrefiksUnit = false): ?string
    {
        foreach (self::daftarAktif() as $entri) {
            if ($abaikanPrefiksUnit && $entri['unit']) {
                continue;
            }

            if (CaraCocok::from($entri['cara'])->cocok($slug, $entri['pola'])) {
                return $entri['pola'];
            }
        }

        return null;
    }
}
