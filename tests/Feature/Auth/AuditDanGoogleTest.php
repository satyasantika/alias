<?php

use App\Enums\MetodeLogin;
use App\Enums\Peran;
use App\Enums\PeristiwaLogin;
use App\Filament\Resources\LogAktivitasResource\Pages\ListLogAktivitas;
use App\Models\Activity;
use App\Models\LogLogin;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('alias');
});

function akunGoogle(string $surel, bool $terverifikasi = true, string $id = 'g-123'): void
{
    $google = (new GoogleUser)->map(['id' => $id, 'email' => $surel, 'name' => 'Nama'])
        ->setRaw(['email_verified' => $terverifikasi]);
    $google->user = ['email_verified' => $terverifikasi];

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($google);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('mencatat perubahan kuota tanpa kolom password', function () {
    $user = User::factory()->create();
    $user->update(['kuota_tautan' => 250]);
    $user->update(['password' => 'kata-sandi-baru-1']);

    $log = Activity::query()->where('event', 'updated')->get();

    expect($log)->toHaveCount(1)
        ->and($log[0]->attribute_changes['attributes'])->toHaveKey('kuota_tautan')
        ->and(json_encode($log[0]->attribute_changes))->not->toContain('password')
        ->and($log[0]->log_name)->toBe('pengguna');
});

it('menampilkan log aktivitas hanya untuk pemegang audit.lihat', function () {
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $pengguna->update(['kuota_tautan' => 5]);

    $this->actingAs($pengguna)->get('/panel/log-aktivitas')->assertForbidden();
    $this->actingAs($admin)->get('/panel/log-aktivitas')->assertOk();

    Livewire::actingAs($admin)->test(ListLogAktivitas::class)
        ->assertCanSeeTableRecords(Activity::all())
        ->assertSee('kuota_tautan');
});

it('menjadikan rute Google 404 saat fitur nonaktif', function () {
    config(['alias.login_google' => false]);

    $this->get('/auth/google/arahkan')->assertNotFound();
    $this->get('/auth/google/kembali')->assertNotFound();
    $this->get('/panel/login')->assertDontSee('Masuk dengan Google');
});

it('menampilkan tombol Google dan mengarahkan bila diaktifkan', function () {
    config(['alias.login_google' => true, 'services.google.client_id' => 'x', 'services.google.client_secret' => 'y', 'services.google.redirect' => 'http://localhost/auth/google/kembali']);

    $this->get('/panel/login')->assertSee('Masuk dengan Google');
    $this->get('/auth/google/arahkan')->assertRedirectContains('accounts.google.com')->assertRedirectContains('hd=%2A');
});

it('menolak akun gmail', function () {
    config(['alias.login_google' => true]);
    akunGoogle('orang@gmail.com');

    $this->get('/auth/google/kembali')->assertRedirect(Filament::getPanel('alias')->getLoginUrl());
    $this->assertGuest();
    expect(LogLogin::where('peristiwa', PeristiwaLogin::DitolakDomain)->where('metode', MetodeLogin::Google)->exists())->toBeTrue();
});

it('menolak surel Google yang belum terverifikasi', function () {
    config(['alias.login_google' => true]);
    User::factory()->create(['email' => 'dosen.a@unsil.ac.id']);
    akunGoogle('dosen.a@unsil.ac.id', terverifikasi: false);

    $this->get('/auth/google/kembali');
    $this->assertGuest();
});

it('menolak akun unsil yang belum terdaftar dengan arahan minta akses', function () {
    config(['alias.login_google' => true]);
    akunGoogle('baru@unsil.ac.id');

    $this->get('/auth/google/kembali')
        ->assertRedirect(Filament::getPanel('alias')->getLoginUrl())
        ->assertSessionHas('status', fn (string $s) => str_contains($s, 'minta-akses'));

    $this->assertGuest();
    expect(User::where('email', 'baru@unsil.ac.id')->exists())->toBeFalse();
});

it('memasukkan akun terdaftar dan menyimpan google_id', function () {
    config(['alias.login_google' => true]);
    $user = User::factory()->create(['email' => 'dosen.a@unsil.ac.id'])->assignRole(Peran::Pengguna->value);
    akunGoogle('dosen.a@unsil.ac.id');

    $this->get('/auth/google/kembali')->assertRedirect();

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->google_id)->toBe('g-123')
        ->and($user->fresh()->metode_login_terakhir)->toBe('google')
        ->and(LogLogin::where('peristiwa', PeristiwaLogin::Berhasil)->where('metode', MetodeLogin::Google)->exists())->toBeTrue();
});

it('menolak google_id berbeda dan akun nonaktif', function () {
    config(['alias.login_google' => true]);
    $user = User::factory()->create(['email' => 'dosen.a@unsil.ac.id']);
    $user->forceFill(['google_id' => 'lain'])->save();
    akunGoogle('dosen.a@unsil.ac.id');

    $this->get('/auth/google/kembali');
    $this->assertGuest();

    $user->forceFill(['google_id' => null, 'aktif' => false])->save();
    $this->get('/auth/google/kembali');
    $this->assertGuest();
});

it('tidak mengizinkan peran admin masuk lewat Google', function () {
    config(['alias.login_google' => true]);
    User::factory()->create(['email' => 'admin.alias@unsil.ac.id'])->assignRole(Peran::AdminAlias->value);
    akunGoogle('admin.alias@unsil.ac.id');

    $this->get('/auth/google/kembali');
    $this->assertGuest();
});
