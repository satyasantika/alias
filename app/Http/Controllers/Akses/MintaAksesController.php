<?php

namespace App\Http\Controllers\Akses;

use App\Actions\Akses\AjukanPermintaanAkses;
use App\Actions\Akses\VerifikasiSurelPermintaan;
use App\Http\Controllers\Controller;
use App\Models\PermintaanAkses;
use App\Models\Unit;
use App\Support\Kunjungan\GaramHarian;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MintaAksesController extends Controller
{
    public function formulir(): View
    {
        return view('akses.formulir', [
            'unit' => Unit::query()->where('aktif', true)->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function kirim(Request $request, AjukanPermintaanAkses $ajukan): RedirectResponse
    {
        $request->validate(['setuju' => ['accepted']], ['setuju.accepted' => 'Anda harus menyetujui pemberitahuan privasi.']);

        // Honeypot: bot mengisi bidang tersembunyi → pura-pura berhasil, tanpa menyimpan apa pun.
        if ($request->filled('website')) {
            return redirect()->route('akses.formulir')->with('terkirim', true);
        }

        $ajukan->jalankan(
            $request->only(['nama', 'email', 'nip', 'unit_id', 'alasan']),
            GaramHarian::hashIp((string) $request->ip()),
        );

        return redirect()->route('akses.formulir')->with('terkirim', true);
    }

    public function verifikasi(PermintaanAkses $permintaan, VerifikasiSurelPermintaan $verifikasi): View
    {
        return view('akses.verifikasi', ['berhasil' => $verifikasi->jalankan($permintaan)]);
    }
}
