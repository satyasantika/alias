<?php

namespace App\Http\Controllers;

use App\Support\Pengaturan;
use Illuminate\Contracts\View\View;

/** Pemberitahuan privasi (UU PDP: transparansi). Pasal rujukan sengaja tidak dikutip sebelum diverifikasi. */
class PrivasiController extends Controller
{
    public function __invoke(): View
    {
        return view('privasi', [
            'ringkasan' => (string) Pengaturan::ambil('teks_pemberitahuan_privasi', ''),
            'retensiBulan' => (int) Pengaturan::ambil('retensi_kunjungan_bulan', 12),
            'retensiBotHari' => (int) Pengaturan::ambil('retensi_kunjungan_bot_hari', 30),
            'retensiLogLoginHari' => (int) Pengaturan::ambil('retensi_log_login_hari', 90),
        ]);
    }
}
