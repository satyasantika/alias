<?php

namespace App\Actions\Pengalihan;

use App\Models\TautanPendek;
use Illuminate\Support\Facades\DB;

/**
 * BR-13: UPDATE bersyarat atomik. Hanya bila baris yang terdampak = 1 pengunjung boleh dialihkan, sehingga
 * dua permintaan serentak tidak dapat sama-sama menghabiskan klik terakhir / tautan sekali pakai.
 */
class KonsumsiKlik
{
    public function jalankan(TautanPendek $tautan): bool
    {
        $sekarang = now()->format('Y-m-d H:i:s');

        $kolom = ['jumlah_klik' => DB::raw('jumlah_klik + 1'), 'klik_terakhir_pada' => $sekarang];
        if ($tautan->sekali_pakai) {
            $kolom['dipakai_pada'] = $sekarang;
        }

        $terdampak = DB::table('tautan_pendek')
            ->where('id', $tautan->getKey())
            ->whereNull('deleted_at')
            ->where('status', 'aktif')
            ->where(fn ($q) => $q->whereNull('batas_klik')->orWhereColumn('jumlah_klik', '<', 'batas_klik'))
            ->when($tautan->sekali_pakai, fn ($q) => $q->whereNull('dipakai_pada'))
            ->update($kolom);

        return $terdampak === 1;
    }

    public static function berlaku(TautanPendek $tautan): bool
    {
        return $tautan->sekali_pakai || $tautan->batas_klik !== null;
    }
}
