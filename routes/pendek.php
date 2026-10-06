<?php

use App\Http\Controllers\Pendek\AlihkanTautanController;
use App\Support\Kode\DaftarSegmenRute;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rute pengalihan (dimuat PALING AKHIR; grup middleware 'pendek' tanpa sesi/CSRF/cookie)
|--------------------------------------------------------------------------
| Urutan (02-ARSITEKTUR §5.2): pratinjau → kata sandi → pengalihan. Batasan `where` + negative lookahead
| memastikan segmen sistem (panel, horizon, api, ...) tidak pernah tertangkap sebagai kode (BR-36).
*/
$daftar = function (): void {
    $kode = DaftarSegmenRute::polaKode();

    Route::get('/{kode}', AlihkanTautanController::class)
        ->where('kode', $kode)
        ->middleware('throttle:pengalihan')
        ->name('pendek.alihkan');
};

if (filled(config('alias.domain_pendek'))) {
    Route::domain((string) config('alias.domain_pendek'))->group($daftar);
} else {
    $daftar();
}
