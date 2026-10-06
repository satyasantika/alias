<?php

namespace App\Support\Kode;

use App\Models\TautanPendek;

/** Kueri pengalihan (03-SKEMA §4.4): satu baris via indeks unik `kode`, termasuk yang terhapus. */
class PencariTautan
{
    public const KOLOM = [
        'id', 'kode', 'kode_kustom', 'judul', 'url_tujuan', 'host_tujuan', 'status', 'alasan_status', 'aktif_mulai',
        'aktif_sampai', 'batas_klik', 'sekali_pakai', 'dipakai_pada', 'jumlah_klik', 'teruskan_query', 'catat_kunjungan',
        'kode_status_redirect', 'kata_sandi_hash', 'jenis_kepemilikan', 'pemilik_id', 'unit_id', 'created_at', 'deleted_at',
    ];

    /** BR-12: pencocokan persis; fallback huruf kecil hanya untuk slug kustom. */
    public function cari(string $kode): ?TautanPendek
    {
        $tautan = TautanPendek::withTrashed()->select(self::KOLOM)->where('kode', $kode)->first();

        if ($tautan === null && $kode !== strtolower($kode)) {
            $tautan = TautanPendek::withTrashed()->select(self::KOLOM)
                ->where('kode', strtolower($kode))->where('kode_kustom', true)->first();
        }

        return $tautan;
    }
}
