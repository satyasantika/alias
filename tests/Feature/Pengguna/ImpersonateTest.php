<?php

use App\Enums\Peran;
use App\Filament\Resources\PenggunaResource\Pages\ListPengguna;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use STS\FilamentImpersonate\Facades\Impersonation;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class]);
    Filament::setCurrentPanel('alias');
    $this->super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);
    $this->super->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->dosen = User::factory()->create()->assignRole(Peran::Pengguna->value);
});

it('menampilkan aksi impersonate hanya untuk super admin', function () {
    Livewire::actingAs($this->super)->test(ListPengguna::class)
        ->assertTableActionVisible('impersonate', $this->dosen);

    Livewire::actingAs($this->admin)->test(ListPengguna::class)
        ->assertTableActionHidden('impersonate', $this->dosen);
});

it('mengizinkan super admin menyamar sebagai pengguna lain dan kembali', function () {
    Livewire::actingAs($this->super)->test(ListPengguna::class)
        ->callTableAction('impersonate', $this->dosen);

    expect(Impersonation::isImpersonating())->toBeTrue()
        ->and(auth()->id())->toBe($this->dosen->id);

    $this->get(route('filament-impersonate.leave'));

    expect(Impersonation::isImpersonating())->toBeFalse();
});

it('tidak mengizinkan menyamar sebagai sesama atau sebagai super admin lain', function () {
    $superLain = User::factory()->create()->assignRole(Peran::SuperAdmin->value);

    Livewire::actingAs($this->super)->test(ListPengguna::class)
        ->assertTableActionHidden('impersonate', $this->super)
        ->assertTableActionHidden('impersonate', $superLain);
});
