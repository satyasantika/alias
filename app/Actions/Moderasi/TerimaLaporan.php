<?php

namespace App\Actions\Moderasi;

use App\Actions\Tautan\BlokirTautan;
use App\Enums\KategoriLaporan;
use App\Enums\StatusLaporan;
use App\Enums\StatusTautan;
use App\Events\LaporanPenyalahgunaanDiterima;
use App\Models\LaporanPenyalahgunaan;
use App\Models\TautanPendek;
use App\Support\Kode\PencariTautan;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/** US-MOD-01 dan BR-37. */
class TerimaLaporan
{
    public const ALASAN_OTOMATIS = 'Otomatis: menunggu tinjauan moderator';

    /**
     * @param  array{kode: string, kategori: string, keterangan?: ?string, email?: ?string}  $data
     */
    public function jalankan(array $data, string $ipHash): LaporanPenyalahgunaan
    {
        $data['kode'] = self::ekstrakKode((string) ($data['kode'] ?? ''));

        Validator::make($data, [
            'kode' => ['required', 'string', 'max:64'],
            'kategori' => ['required', Rule::enum(KategoriLaporan::class)],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'email' => ['nullable', 'email', 'max:255'],
        ], attributes: ['kode' => 'kode tautan'])->validate();

        $tautan = app(PencariTautan::class)->cari($data['kode']);
        $kategori = KategoriLaporan::from($data['kategori']);

        $laporan = LaporanPenyalahgunaan::create([
            'tautan_pendek_id' => $tautan?->getKey(),
            'kode_dilaporkan' => $data['kode'],
            'kategori' => $kategori,
            'keterangan' => filled($data['keterangan'] ?? null) ? trim(strip_tags((string) $data['keterangan'])) : null,
            'email_pelapor' => filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null,
            'ip_hash' => $ipHash,
        ]);

        if ($tautan !== null && $kategori->memicuBlokirOtomatis()) {
            $this->blokirBilaMelampauiAmbang($tautan, $laporan);
        }

        LaporanPenyalahgunaanDiterima::dispatch($laporan);

        return $laporan;
    }

    /** Menerima kode, URL pendek lengkap, atau "kode+" dan mengembalikan kodenya saja. */
    public static function ekstrakKode(string $masukan): string
    {
        $masukan = trim($masukan);

        if (str_contains($masukan, '://')) {
            $masukan = (string) parse_url($masukan, PHP_URL_PATH);
        }

        return rtrim(trim($masukan, '/ '), '+');
    }

    /** BR-37: ≥ ambang laporan (phishing/malware/judi) dari ip_hash berbeda dalam 24 jam. */
    private function blokirBilaMelampauiAmbang(TautanPendek $tautan, LaporanPenyalahgunaan $laporan): void
    {
        $ambang = (int) Pengaturan::ambil('ambang_blokir_otomatis', 3);

        if ($ambang <= 0 || ! in_array($tautan->status, [StatusTautan::Aktif, StatusTautan::Dinonaktifkan], true)) {
            return;
        }

        $pelapor = LaporanPenyalahgunaan::query()
            ->where('tautan_pendek_id', $tautan->getKey())
            ->whereIn('kategori', array_map(fn (KategoriLaporan $k) => $k->value, array_filter(KategoriLaporan::cases(), fn (KategoriLaporan $k) => $k->memicuBlokirOtomatis())))
            ->where('created_at', '>=', now()->subDay())
            ->distinct()
            ->count('ip_hash');

        if ($pelapor < $ambang) {
            return;
        }

        app(BlokirTautan::class)->jalankan($tautan, self::ALASAN_OTOMATIS, null);

        LaporanPenyalahgunaan::query()
            ->where('tautan_pendek_id', $tautan->getKey())
            ->where('status', StatusLaporan::Baru)
            ->update(['tindakan' => 'blokir']);
    }
}
