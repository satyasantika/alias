<?php

namespace App\Http\Controllers\Pendek;

use App\Http\Controllers\Controller;
use App\Models\TautanPendek;
use App\Support\Tujuan\TokenKataSandi;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\RedirectResponse;

/** POST /{kode} (BR-35): 5 percobaan/menit per IP+kode (throttle:kata-sandi-tautan). */
class KataSandiTautanController extends Controller
{
    public function __construct(private readonly AlihkanTautanController $pengalihan) {}

    public function __invoke(Request $request, string $kode): Response|RedirectResponse
    {
        $tautan = $this->pengalihan->siapkan($kode);

        if (! $tautan instanceof TautanPendek) {
            return $tautan;
        }

        // Tautan tanpa kata sandi: kembali ke alur biasa.
        if ($tautan->kata_sandi_hash === null) {
            return redirect()->to(url('/'.$tautan->kode));
        }

        $token = (string) $request->input('token', '');
        $kataSandi = (string) $request->input('kata_sandi', '');

        if (! TokenKataSandi::sah($kode, $token)) {
            return $this->pengalihan->formKataSandi($tautan, $kode, 'Formulir kedaluwarsa. Silakan coba lagi.');
        }

        if ($kataSandi === '' || ! Hash::check($kataSandi, $tautan->kata_sandi_hash)) {
            return $this->pengalihan->formKataSandi($tautan, $kode, 'Kata sandi salah.');
        }

        return $this->pengalihan->lanjutkan($request, $tautan, $kode);
    }
}
