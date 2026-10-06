<?php

namespace App\Models;

use App\Enums\JenisAturanDomain;
use App\Jobs\PindaiUlangAturan;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * @property JenisAturanDomain $jenis
 * @property string $pola_host
 */
class AturanDomain extends Model
{
    use HasUuids, TercatatAktivitas;

    public const KUNCI_CACHE = 'alias:aturan-domain';

    protected $table = 'aturan_domain';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $attributes = ['aktif' => true];

    protected $fillable = ['pola_host', 'jenis', 'alasan', 'aktif', 'dibuat_oleh'];

    protected function casts(): array
    {
        return ['jenis' => JenisAturanDomain::class, 'aktif' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saving(fn (self $m) => $m->pola_host = mb_strtolower(trim($m->pola_host)));
        static::saved(function (self $m): void {
            Cache::forget(self::KUNCI_CACHE);

            if ($m->aktif && (($m->wasRecentlyCreated && $m->getChanges() === []) || $m->wasChanged(['pola_host', 'jenis', 'aktif']))) {
                PindaiUlangAturan::kerjakan('domain', $m->getKey());
            }
        });
        static::deleted(fn () => Cache::forget(self::KUNCI_CACHE));
    }

    protected function namaLog(): string
    {
        return 'moderasi';
    }

    /**
     * Aturan aktif (ber-cache) dipisah per jenis.
     *
     * @return array{blokir: list<string>, izinkan: list<string>}
     */
    public static function daftarAktif(): array
    {
        return Cache::remember(self::KUNCI_CACHE, 3600, function (): array {
            $hasil = ['blokir' => [], 'izinkan' => []];
            foreach (self::query()->where('aktif', true)->get(['pola_host', 'jenis']) as $aturan) {
                $hasil[$aturan->jenis->value][] = $aturan->pola_host;
            }

            return $hasil;
        });
    }
}
