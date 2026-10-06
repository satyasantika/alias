<?php

namespace App\Actions\Tautan;

use App\Enums\StatusTautan;
use App\Events\StatusTautanBerubah;
use App\Models\RiwayatStatusTautan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * BR-26: satu-satunya jalur perubahan status. Memvalidasi transisi (diagram PRD §8.1), mewajibkan alasan
 * ≥ 10 karakter bila diminta, dan menulis riwayat_status_tautan.
 */
class UbahStatusTautan
{
    public const ALASAN_MINIMAL = 10;

    /** @var array<string, list<string>> */
    public const TRANSISI = [
        'menunggu_persetujuan' => ['aktif', 'ditolak'],
        'ditolak' => ['menunggu_persetujuan'],
        'aktif' => ['dinonaktifkan', 'diblokir'],
        'dinonaktifkan' => ['aktif', 'diblokir'],
        'diblokir' => ['aktif'],
    ];

    public static function sah(StatusTautan $dari, StatusTautan $ke): bool
    {
        return in_array($ke->value, self::TRANSISI[$dari->value] ?? [], true);
    }

    /** @param  array<string, mixed>  $tambahan  kolom lain yang ikut diubah pada transaksi yang sama */
    public function terapkan(TautanPendek $tautan, StatusTautan $ke, ?User $oleh, ?string $alasan, bool $alasanWajib, array $tambahan = []): TautanPendek
    {
        $dari = $tautan->status;
        $alasan = $alasan !== null ? trim($alasan) : null;

        if (! self::sah($dari, $ke)) {
            throw ValidationException::withMessages([
                'status' => "Transisi dari \"{$dari->getLabel()}\" ke \"{$ke->getLabel()}\" tidak diizinkan.",
            ]);
        }

        if ($alasanWajib && mb_strlen((string) $alasan) < self::ALASAN_MINIMAL) {
            throw ValidationException::withMessages([
                'alasan' => 'Alasan wajib diisi (minimal '.self::ALASAN_MINIMAL.' karakter).',
            ]);
        }

        return DB::transaction(function () use ($tautan, $dari, $ke, $oleh, $alasan, $tambahan): TautanPendek {
            $tautan->forceFill([
                'status' => $ke,
                'alasan_status' => $alasan !== '' ? $alasan : null,
                ...$tambahan,
            ]);

            if ($ke === StatusTautan::Aktif && $tautan->pertama_aktif_pada === null) {
                $tautan->pertama_aktif_pada = now();
            }

            $tautan->save();

            RiwayatStatusTautan::create([
                'tautan_pendek_id' => $tautan->getKey(),
                'dari_status' => $dari,
                'ke_status' => $ke,
                'alasan' => $alasan !== '' ? $alasan : null,
                'oleh' => $oleh?->getKey(),
            ]);

            StatusTautanBerubah::dispatch($tautan, $dari, $ke, $oleh, $alasan !== '' ? $alasan : null);

            return $tautan;
        });
    }

    /** Pelaku dianggap "pemilik" (alasan tidak wajib) bila pemilik pribadi, pengelola unit, atau pembuat tautan unit. */
    public static function pemilikTindakan(TautanPendek $tautan, User $oleh): bool
    {
        if ($tautan->milikPribadi($oleh)) {
            return true;
        }

        return $tautan->unit !== null && ($oleh->kelolaUnit($tautan->unit) || $tautan->dibuat_oleh === $oleh->getKey());
    }
}
