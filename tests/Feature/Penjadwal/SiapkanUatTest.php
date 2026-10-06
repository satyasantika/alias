<?php

use App\Enums\StatusTautan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('menyiapkan akun uji per peran dengan MFA admin dan keanggotaan unit', function () {
    $this->artisan('alias:siapkan-uat')->assertSuccessful();

    $u = fn (string $s) => User::where('email', $s)->firstOrFail();
    expect($u('superadmin@unsil.ac.id')->hasRole('super-admin'))->toBeTrue()
        ->and($u('superadmin@unsil.ac.id')->getAppAuthenticationSecret())->not->toBeNull()
        ->and($u('admin.alias@unsil.ac.id')->getAppAuthenticationSecret())->not->toBeNull()
        ->and($u('pengelola.pmat@unsil.ac.id')->kelolaUnit(Unit::where('kode', 'PMAT')->first()))->toBeTrue()
        ->and($u('dosen.a@unsil.ac.id')->anggotaUnit(Unit::where('kode', 'PMAT')->first()))->toBeTrue()
        ->and($u('dosen.b@unsil.ac.id')->anggotaUnit(Unit::where('kode', 'PMAT')->first()))->toBeFalse()
        ->and($u('dosen.b@unsil.ac.id')->anggotaUnit(Unit::where('kode', 'PBIO')->first()))->toBeTrue()
        ->and($u('wakil.dekan@unsil.ac.id')->hasRole('pemantau'))->toBeTrue()
        ->and($u('dosen.a@unsil.ac.id')->getAppAuthenticationSecret())->toBeNull()
        ->and(Hash::check(config('alias.seed_password'), $u('dosen.a@unsil.ac.id')->password))->toBeTrue();
});

it('membuat tautan contoh berbagai status dan dapat dijalankan berulang (idempoten)', function () {
    $this->artisan('alias:siapkan-uat')->assertSuccessful();
    $jumlahAkun = User::count();
    $jumlahTautan = TautanPendek::count();
    $this->artisan('alias:siapkan-uat')->assertSuccessful();

    expect(User::count())->toBe($jumlahAkun)->and(TautanPendek::count())->toBe($jumlahTautan);

    $status = fn (string $kode) => TautanPendek::where('kode', $kode)->first()->status;
    expect($status('UatAktif'))->toBe(StatusTautan::Aktif)
        ->and($status('seminar-pmat-2026'))->toBe(StatusTautan::MenungguPersetujuan)
        ->and($status('uat-ditolak'))->toBe(StatusTautan::Ditolak)
        ->and($status('uat-nonaktif'))->toBe(StatusTautan::Dinonaktifkan)
        ->and($status('uat-diblokir'))->toBe(StatusTautan::Diblokir);
});

it('menghasilkan perilaku pengalihan yang benar untuk tautan contoh', function () {
    $this->artisan('alias:siapkan-uat')->assertSuccessful();

    $this->get('/UatAktif')->assertStatus(302);
    $this->get('/seminar-pmat-2026')->assertStatus(404);
    $this->get('/uat-ditolak')->assertStatus(404);
    $this->get('/uat-nonaktif')->assertStatus(410);
    $this->get('/uat-diblokir')->assertStatus(410);
    $this->get('/uat-besok')->assertStatus(404);
    $this->get('/uat-lewat')->assertStatus(410);
    $this->get('/uat-sandi')->assertOk()->assertSee('dilindungi kata sandi');
    $this->get('/uat-sekali')->assertStatus(302);
    $this->get('/uat-sekali')->assertStatus(410);
});

it('menolak berjalan di produksi atau tanpa SEED_PASSWORD', function () {
    config(['alias.seed_password' => null]);
    $this->artisan('alias:siapkan-uat')->assertFailed();

    config(['alias.seed_password' => 'uat-sandi']);
    app()->detectEnvironment(fn () => 'production');
    $this->artisan('alias:siapkan-uat')->assertFailed();
    expect(User::where('email', 'superadmin@unsil.ac.id')->exists())->toBeFalse();
});
