<?php

use App\Enums\Peran;
use App\Models\TautanPendek;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    $this->pemilik = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->tautan = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Qrl0001']);
});

it('mendefinisikan seluruh batas laju BR-24 dari config', function () {
    expect(config('alias.batas_laju'))->toBe([
        'pengalihan' => [300, 1], 'pratinjau' => [60, 1], 'buat-tautan' => [30, 60], 'lapor' => [5, 60],
        'minta-akses' => [3, 60], 'qr' => [60, 1], 'kata-sandi-tautan' => [5, 1],
    ]);

    foreach (array_keys(config('alias.batas_laju')) as $nama) {
        expect(RateLimiter::limiter($nama))->not->toBeNull($nama);
    }
});

it('membatasi QR 60 per menit per pengguna (bukan per IP)', function () {
    foreach (range(1, 60) as $_) {
        $this->actingAs($this->pemilik)->get("/panel/tautan/{$this->tautan->id}/qr.svg")->assertOk();
    }

    $this->actingAs($this->pemilik)->get("/panel/tautan/{$this->tautan->id}/qr.svg")->assertStatus(429);

    $lain = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $milikLain = TautanPendek::factory()->milikPribadi($lain)->create();
    $this->actingAs($lain)->get("/panel/tautan/{$milikLain->id}/qr.svg")->assertOk();
});

it('menerapkan throttle pada seluruh rute publik sensitif', function () {
    $middleware = fn (string $nama) => collect(Route::getRoutes()->getRoutes())
        ->first(fn ($r) => $r->getName() === $nama)->gatherMiddleware();

    expect($middleware('pendek.alihkan'))->toContain('throttle:pengalihan')
        ->and($middleware('pendek.pratinjau'))->toContain('throttle:pratinjau')
        ->and($middleware('pendek.kata-sandi'))->toContain('throttle:kata-sandi-tautan')
        ->and($middleware('lapor.kirim'))->toContain('throttle:lapor')
        ->and($middleware('akses.kirim'))->toContain('throttle:minta-akses')
        ->and($middleware('tautan.qr'))->toContain('throttle:qr');
});
