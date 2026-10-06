<?php

namespace App\Support;

use App\Models\Pengaturan as Model;
use Illuminate\Support\Facades\Cache;

/** Akses tabel `pengaturan` ber-cache (kunci `alias:pengaturan`, TTL 1 jam, dibersihkan saat simpan). */
class Pengaturan
{
    public const KUNCI_CACHE = 'alias:pengaturan';

    /** @return array<string, array{nilai: ?string, tipe: string}> */
    private static function semua(): array
    {
        return Cache::remember(self::KUNCI_CACHE, 3600, fn (): array => Model::query()->get(['kunci', 'nilai', 'tipe'])
            ->mapWithKeys(fn (Model $m) => [$m->kunci => ['nilai' => $m->nilai, 'tipe' => $m->tipe]])
            ->all());
    }

    public static function ambil(string $kunci, mixed $bawaan = null): mixed
    {
        $baris = self::semua()[$kunci] ?? null;

        if ($baris === null || $baris['nilai'] === null) {
            return $bawaan;
        }

        return match ($baris['tipe']) {
            'bool' => filter_var($baris['nilai'], FILTER_VALIDATE_BOOLEAN),
            'int' => (int) $baris['nilai'],
            'list' => array_values(array_filter(array_map('trim', preg_split('/\R/', $baris['nilai']) ?: []), fn ($v) => $v !== '')),
            default => $baris['nilai'],
        };
    }

    public static function atur(string $kunci, mixed $nilai, ?string $diubahOleh = null): void
    {
        $model = Model::query()->where('kunci', $kunci)->firstOrFail();
        $model->update([
            'nilai' => is_array($nilai) ? implode("\n", $nilai) : (is_bool($nilai) ? ($nilai ? '1' : '0') : (string) $nilai),
            'diubah_oleh' => $diubahOleh,
        ]);
    }
}
