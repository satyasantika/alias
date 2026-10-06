<?php

namespace App\Actions\Tautan;

use App\Jobs\PeriksaKesehatanTujuan;
use App\Models\TautanPendek;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Mengubah atribut tautan; kode dan kepemilikan tidak berubah di sini (lihat transfer, F4.6). */
class UbahTautan
{
    use ValidasiTautan;

    /**
     * @param  array<string, mixed>  $data
     */
    public function jalankan(TautanPendek $tautan, array $data, User $oleh): TautanPendek
    {
        Gate::forUser($oleh)->authorize('update', $tautan);

        $tujuanBerubah = array_key_exists('url_tujuan', $data) && $data['url_tujuan'] !== $tautan->url_tujuan;
        $tujuan = $tujuanBerubah ? $this->validasiTujuan((string) $data['url_tujuan'], $oleh) : null;

        $atribut = $this->atributBersama([
            'judul' => $tautan->judul,
            'keterangan' => $tautan->keterangan,
            'aktif_mulai' => $tautan->aktif_mulai,
            'aktif_sampai' => $tautan->aktif_sampai,
            'batas_klik' => $tautan->batas_klik,
            'sekali_pakai' => $tautan->sekali_pakai,
            'teruskan_query' => $tautan->teruskan_query,
            'kode_status_redirect' => $tautan->kode_status_redirect,
            'catat_kunjungan' => $tautan->catat_kunjungan,
            ...$data,
        ], $oleh);

        DB::transaction(function () use ($tautan, $atribut, $tujuan): void {
            $tautan->fill($atribut);

            if ($tujuan !== null) {
                $tautan->fill([
                    'url_tujuan' => $tujuan['url'],
                    'host_tujuan' => $tujuan['host'],
                    'url_tujuan_hash' => $tujuan['hash'],
                ]);
                $tautan->forceFill([
                    'status_cek_tujuan' => 'belum', 'gagal_cek_beruntun' => 0, 'kode_http_terakhir' => null, 'dicek_tujuan_pada' => null,
                ]);
            }

            $tautan->save();
        });

        if ($tujuan !== null) {
            PeriksaKesehatanTujuan::untuk($tautan);
        }

        return $tautan;
    }
}
