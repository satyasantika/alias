<?php

use App\Actions\Pengguna\AturPeranPengguna;
use App\Actions\Pengguna\BuatPengguna;
use App\Actions\Pengguna\NonaktifkanPengguna;
use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\PenggunaResource\Pages\CreatePengguna;
use App\Filament\Resources\PenggunaResource\Pages\ListPengguna;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AturKataSandiAkun;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function aktorMfa(Peran $peran): User
{
    $u = User::factory()->create()->assignRole($peran->value);
    $u->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    return $u;
}

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class]);
    Filament::setCurrentPanel('alias');
    $this->admin = aktorMfa(Peran::AdminAlias);
    $this->super = aktorMfa(Peran::SuperAdmin);
});

it('membuat akun tanpa kata sandi dan mengirim surel atur kata sandi', function () {
    Notification::fake();

    $user = app(BuatPengguna::class)->jalankan([
        'name' => 'Dosen Baru', 'email' => 'Dosen.Baru@unsil.ac.id', 'peran' => [Peran::Pengguna->value],
    ], $this->admin);

    expect($user->email)->toBe('dosen.baru@unsil.ac.id')
        ->and($user->password)->toBeNull()
        ->and($user->hasRole('pengguna'))->toBeTrue()
        ->and($user->aktif)->toBeTrue();
    Notification::assertSentTo($user, AturKataSandiAkun::class, function ($n) use ($user) {
        $url = $n->toMail($user)->actionUrl;

        return str_contains($url, '/panel/password-reset/reset') && str_contains($url, 'signature=');
    });
});

it('menolak surel di luar domain unsil dan surel ganda', function () {
    expect(fn () => app(BuatPengguna::class)->jalankan(['name' => 'X', 'email' => 'x@gmail.com'], $this->admin))
        ->toThrow(ValidationException::class);

    app(BuatPengguna::class)->jalankan(['name' => 'A', 'email' => 'a@unsil.ac.id'], $this->admin);
    expect(fn () => app(BuatPengguna::class)->jalankan(['name' => 'A2', 'email' => 'a@unsil.ac.id'], $this->admin))
        ->toThrow(ValidationException::class);
});

it('melarang admin-alias memberi peran admin (BR-33)', function () {
    $target = User::factory()->create();

    expect(fn () => app(AturPeranPengguna::class)->jalankan($target, [Peran::AdminAlias->value], $this->admin))
        ->toThrow(ValidationException::class);
    expect(fn () => app(BuatPengguna::class)->jalankan(['name' => 'Z', 'email' => 'z@unsil.ac.id', 'peran' => [Peran::SuperAdmin->value]], $this->admin))
        ->toThrow(ValidationException::class);

    app(AturPeranPengguna::class)->jalankan($target, [Peran::AdminAlias->value], $this->super);
    expect($target->fresh()->hasRole('admin-alias'))->toBeTrue();
});

it('tidak menampilkan opsi peran admin kepada admin-alias', function () {
    Livewire::actingAs($this->admin)->test(CreatePengguna::class)
        ->assertFormFieldExists('peran', function ($field) {
            return array_keys($field->getOptions()) === ['pengelola-unit', 'pengguna', 'pemantau'];
        });

    Livewire::actingAs($this->super)->test(CreatePengguna::class)
        ->assertFormFieldExists('peran', fn ($field) => count($field->getOptions()) === 5);
});

it('menolak mencabut atau menonaktifkan super-admin aktif terakhir', function () {
    expect(fn () => app(AturPeranPengguna::class)->jalankan($this->super, [Peran::Pengguna->value], $this->super))
        ->toThrow(ValidationException::class);

    $super2 = aktorMfa(Peran::SuperAdmin);
    expect(fn () => app(NonaktifkanPengguna::class)->jalankan($this->super, $this->super, null))->toThrow(ValidationException::class);

    app(NonaktifkanPengguna::class)->jalankan($super2, $this->super, null);
    expect($super2->fresh()->aktif)->toBeFalse();

    // super2 nonaktif → super pertama kini yang terakhir.
    expect(fn () => app(AturPeranPengguna::class)->jalankan($this->super, [Peran::AdminAlias->value], $this->super))
        ->toThrow(ValidationException::class);
});

it('melarang admin-alias menonaktifkan akun admin', function () {
    $adminLain = aktorMfa(Peran::AdminAlias);

    expect(fn () => app(NonaktifkanPengguna::class)->jalankan($adminLain, $this->admin, null))
        ->toThrow(AuthorizationException::class);
});

it('menonaktifkan akun biasa dan menolak login-nya', function () {
    $user = User::factory()->create(['email' => 'dosen.x@unsil.ac.id'])->assignRole(Peran::Pengguna->value);

    app(NonaktifkanPengguna::class)->jalankan($user, $this->admin, Unit::where('kode', 'PMAT')->first());

    expect($user->fresh()->aktif)->toBeFalse();
    Livewire::test(Login::class)->fillForm(['email' => $user->email, 'password' => 'password'])
        ->call('authenticate')->assertHasFormErrors(['email']);
});

it('membatasi pengelola-unit hanya melihat anggota unitnya', function () {
    $pmat = Unit::where('kode', 'PMAT')->first();
    $pengelola = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);
    app(TambahAnggotaUnit::class)->jalankan($pmat, $pengelola, PeranUnit::Pengelola, $this->admin);
    $anggota = User::factory()->create();
    app(TambahAnggotaUnit::class)->jalankan($pmat, $anggota, PeranUnit::Anggota, $pengelola);
    $luar = User::factory()->create();

    Livewire::actingAs($pengelola)->test(ListPengguna::class)
        ->assertCanSeeTableRecords([$anggota, $pengelola])
        ->assertCanNotSeeTableRecords([$luar]);

    expect($pengelola->can('view', $anggota))->toBeTrue()
        ->and($pengelola->can('view', $luar))->toBeFalse()
        ->and($pengelola->can('create', User::class))->toBeFalse()
        ->and($pengelola->can('update', $anggota))->toBeFalse();
});

it('menolak pengguna biasa dan pemantau membuka daftar pengguna', function () {
    $this->actingAs(User::factory()->create()->assignRole(Peran::Pengguna->value))->get('/panel/pengguna')->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole(Peran::Pemantau->value))->get('/panel/pengguna')->assertForbidden();
});

it('tidak pernah menghapus akun permanen lewat policy', function () {
    $target = User::factory()->create();

    expect($this->super->can('delete', $target))->toBeTrue(); // Gate::before super-admin
    expect($this->admin->can('delete', $target))->toBeFalse();
});
