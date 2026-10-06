<?php

namespace App\Jobs;

use App\Enums\CaraCocok;
use App\Enums\JenisAturanDomain;
use App\Enums\KategoriLaporan;
use App\Enums\StatusTautan;
use App\Models\AturanDomain;
use App\Models\LaporanPenyalahgunaan;
use App\Models\SlugTerlarang;
use App\Models\TautanPendek;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;

/**
 * 02-ARSITEKTUR §8: tautan aktif yang melanggar aturan BARU ditandai lewat laporan sistem untuk ditinjau
 * moderator. Tidak memblokir otomatis dan tidak memengaruhi pengalihan.
 */
class PindaiUlangAturan implements ShouldQueue
{
    use Queueable;

    public const HASH_SISTEM = '0000000000000000000000000000000000000000000000000000000000000000';

    /** Dimatikan sementara oleh seeder agar tidak membanjiri antrean. */
    public static bool $aktif = true;

    public function __construct(public readonly string $jenis, public readonly string $aturanId)
    {
        $this->onQueue('default');
    }

    public static function kerjakan(string $jenis, string $aturanId): void
    {
        if (self::$aktif) {
            self::dispatch($jenis, $aturanId)->afterCommit();
        }
    }

    /** @param  callable(): mixed  $blok */
    public static function tanpaPindai(callable $blok): mixed
    {
        $sebelumnya = self::$aktif;
        self::$aktif = false;

        try {
            return $blok();
        } finally {
            self::$aktif = $sebelumnya;
        }
    }

    public function handle(): void
    {
        if ($this->jenis === 'slug') {
            $aturan = SlugTerlarang::query()->find($this->aturanId);
            if ($aturan === null || ! $aturan->aktif || str_starts_with((string) $aturan->keterangan, SlugTerlarang::PENANDA_PREFIKS_UNIT)) {
                return;
            }

            $this->tandai($this->kueriSlug($aturan), "slug dilarang \"{$aturan->pola}\" ({$aturan->cara_cocok->getLabel()})");

            return;
        }

        $aturan = AturanDomain::query()->find($this->aturanId);
        if ($aturan === null || ! $aturan->aktif || $aturan->jenis !== JenisAturanDomain::Blokir) {
            return;
        }

        $this->tandai($this->kueriDomain($aturan->pola_host), "domain tujuan diblokir \"{$aturan->pola_host}\"");
    }

    /** @return Builder<TautanPendek> */
    private function kueriSlug(SlugTerlarang $aturan): Builder
    {
        $pola = mb_strtolower($aturan->pola);
        $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $pola);

        return TautanPendek::query()->where('status', StatusTautan::Aktif->value)->where(fn (Builder $q) => match ($aturan->cara_cocok) {
            CaraCocok::Persis => $q->whereRaw('lower(kode) = ?', [$pola]),
            CaraCocok::Awalan => $q->whereRaw("lower(kode) like ? escape '\\'", [$like.'%']),
            CaraCocok::Mengandung => $q->whereRaw("lower(kode) like ? escape '\\'", ['%'.$like.'%']),
        });
    }

    /** @return Builder<TautanPendek> */
    private function kueriDomain(string $pola): Builder
    {
        $pola = mb_strtolower($pola);
        $query = TautanPendek::query()->where('status', StatusTautan::Aktif->value);

        if (str_starts_with($pola, '*.')) {
            $dasar = substr($pola, 2);
            $like = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $dasar);

            return $query->where(fn (Builder $q) => $q->where('host_tujuan', $dasar)->orWhereRaw("host_tujuan like ? escape '\\'", ['%.'.$like]));
        }

        return $query->where('host_tujuan', $pola);
    }

    /** @param  Builder<TautanPendek>  $kueri */
    private function tandai(Builder $kueri, string $sebab): void
    {
        $keterangan = "Melanggar aturan baru: {$sebab}";

        $kueri->chunkById(200, function ($kelompok) use ($keterangan): void {
            foreach ($kelompok as $tautan) {
                $sudahAda = LaporanPenyalahgunaan::query()
                    ->where('tautan_pendek_id', $tautan->getKey())
                    ->where('ip_hash', self::HASH_SISTEM)
                    ->where('keterangan', $keterangan)
                    ->exists();

                if ($sudahAda) {
                    continue;
                }

                LaporanPenyalahgunaan::create([
                    'tautan_pendek_id' => $tautan->getKey(),
                    'kode_dilaporkan' => $tautan->kode,
                    'kategori' => KategoriLaporan::Lainnya,
                    'keterangan' => $keterangan,
                    'ip_hash' => self::HASH_SISTEM,
                ]);
            }
        });
    }
}
