<?php

namespace App\Http\Controllers\Moderasi;

use App\Actions\Moderasi\TerimaLaporan;
use App\Enums\KategoriLaporan;
use App\Http\Controllers\Controller;
use App\Support\Kunjungan\GaramHarian;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** /lapor: formulir publik laporan penyalahgunaan (BR-24: 5/jam/IP). */
class LaporController extends Controller
{
    public function formulir(Request $request): View
    {
        return view('lapor.formulir', [
            'kategori' => KategoriLaporan::cases(),
            'kode' => (string) $request->query('kode', ''),
        ]);
    }

    public function kirim(Request $request, TerimaLaporan $terima): RedirectResponse
    {
        // Honeypot: bot mengisi bidang tersembunyi → pura-pura berhasil.
        if ($request->filled('website')) {
            return redirect()->route('lapor.formulir')->with('terkirim', true);
        }

        $terima->jalankan($request->only(['kode', 'kategori', 'keterangan', 'email']), GaramHarian::hashIp((string) $request->ip()));

        return redirect()->route('lapor.formulir')->with('terkirim', true);
    }
}
