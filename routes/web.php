<?php

use App\Http\Controllers\Akses\MintaAksesController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\Moderasi\LaporController;
use App\Http\Controllers\PrivasiController;
use App\Http\Controllers\QrTautanController;
use Illuminate\Support\Facades\Route;

Route::get('/', BerandaController::class)->name('beranda');
Route::get('/privasi', PrivasiController::class)->name('privasi');

Route::middleware('guest')->prefix('auth/google')->group(function () {
    Route::get('arahkan', [GoogleAuthController::class, 'arahkan'])->name('auth.google.arahkan');
    Route::get('kembali', [GoogleAuthController::class, 'kembali'])->name('auth.google.kembali');
});

Route::prefix('minta-akses')->group(function () {
    Route::get('/', [MintaAksesController::class, 'formulir'])->name('akses.formulir');
    Route::post('/', [MintaAksesController::class, 'kirim'])->middleware('throttle:minta-akses')->name('akses.kirim');
    Route::get('verifikasi/{permintaan}', [MintaAksesController::class, 'verifikasi'])->middleware('signed')->name('akses.verifikasi');
});

Route::get('panel/tautan/{tautan}/qr.{format}', QrTautanController::class)
    ->middleware(['auth', 'throttle:qr'])
    ->where('format', 'svg|png')
    ->name('tautan.qr');

Route::get('/lapor', [LaporController::class, 'formulir'])->name('lapor.formulir');
Route::post('/lapor', [LaporController::class, 'kirim'])->middleware('throttle:lapor')->name('lapor.kirim');
