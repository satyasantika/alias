<?php

use App\Actions\Unit\KeluarkanAnggotaUnit;
use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Filament\Resources\UnitResource\Pages\EditUnit;
use App\Filament\Resources\UnitResource\Pages\ListUnit;
use App\Filament\Resources\UnitResource\RelationManagers\AnggotaRelationManager;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class]);
    Filament::setCurrentPanel('alias');
    $this->pmat = Unit::where('kode', 'PMAT')->firstOrFail();
    $this->pbio = Unit::create(['kode' => 'PBIO', 'nama' => 'Pendidikan Biologi', 'jenis' => 'prodi', 'induk_id' => $this->pmat->induk_id]);

    $this->pengelolaPmat = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pengelolaPmat, PeranUnit::Pengelola, User::factory()->create()->assignRole(Peran::AdminAlias->value));
    $this->dosen = User::factory()->create()->assignRole(Peran::Pengguna->value);
});

it('menyemai unit dari CSV secara idempoten dengan hierarki', function () {
    $this->seed(UnitSeeder::class);

    expect(Unit::where('kode', 'FKIP')->count())->toBe(1)
        ->and($this->pmat->induk->kode)->toBe('FKIP')
        ->and($this->pmat->prefiks_slug)->toBe('pmat');
});

it('mengizinkan pengelola PMAT menambah anggota PMAT', function () {
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->dosen, PeranUnit::Anggota, $this->pengelolaPmat);

    expect($this->dosen->anggotaUnit($this->pmat))->toBeTrue()
        ->and($this->dosen->kelolaUnit($this->pmat))->toBeFalse();
});

it('menolak pengelola PMAT menambah anggota unit lain (403)', function () {
    expect(fn () => app(TambahAnggotaUnit::class)->jalankan($this->pbio, $this->dosen, PeranUnit::Anggota, $this->pengelolaPmat))
        ->toThrow(AuthorizationException::class);
});

it('menolak pengguna biasa mengelola anggota', function () {
    expect(fn () => app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->dosen, PeranUnit::Anggota, $this->dosen))
        ->toThrow(AuthorizationException::class);
});

it('menolak mengeluarkan pengelola terakhir', function () {
    expect(fn () => app(KeluarkanAnggotaUnit::class)->jalankan($this->pmat, $this->pengelolaPmat, $this->pengelolaPmat))
        ->toThrow(ValidationException::class);
});

it('mengeluarkan pengelola bila masih ada pengelola lain dan mencabut perannya', function () {
    $pengelolaLain = User::factory()->create();
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $pengelolaLain, PeranUnit::Pengelola, $this->pengelolaPmat);
    expect($pengelolaLain->hasRole('pengelola-unit'))->toBeTrue();

    app(KeluarkanAnggotaUnit::class)->jalankan($this->pmat, $pengelolaLain, $this->pengelolaPmat);

    expect($pengelolaLain->fresh()->anggotaUnit($this->pmat))->toBeFalse()
        ->and($pengelolaLain->fresh()->hasRole('pengelola-unit'))->toBeFalse();
});

it('menolak menambah pengguna nonaktif', function () {
    $nonaktif = User::factory()->create(['aktif' => false]);

    expect(fn () => app(TambahAnggotaUnit::class)->jalankan($this->pmat, $nonaktif, PeranUnit::Anggota, $this->pengelolaPmat))
        ->toThrow(ValidationException::class);
});

it('membatasi daftar unit per peran', function () {
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->dosen, PeranUnit::Anggota, $this->pengelolaPmat);
    $pemantau = User::factory()->create()->assignRole(Peran::Pemantau->value);
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    Livewire::actingAs($this->dosen)->test(ListUnit::class)
        ->assertCanSeeTableRecords([$this->pmat])->assertCanNotSeeTableRecords([$this->pbio]);
    Livewire::actingAs($this->pengelolaPmat)->test(ListUnit::class)
        ->assertCanSeeTableRecords([$this->pmat])->assertCanNotSeeTableRecords([$this->pbio]);
    Livewire::actingAs($pemantau)->test(ListUnit::class)
        ->assertCanSeeTableRecords([$this->pmat, $this->pbio])->assertTableColumnHidden('keanggotaan_count');
    Livewire::actingAs($admin)->test(ListUnit::class)
        ->assertCanSeeTableRecords([$this->pmat, $this->pbio])->assertTableColumnVisible('keanggotaan_count');
});

it('mengizinkan hanya admin membuat unit', function () {
    $this->actingAs($this->pengelolaPmat)->get('/panel/unit/create')->assertForbidden();
    $this->actingAs($this->dosen)->get('/panel/unit/create')->assertForbidden();
});

it('menampilkan relation manager anggota dengan aksi sesuai hak', function () {
    Livewire::actingAs($this->pengelolaPmat)
        ->test(AnggotaRelationManager::class, ['ownerRecord' => $this->pmat, 'pageClass' => EditUnit::class])
        ->assertCanSeeTableRecords($this->pmat->keanggotaan)
        ->assertActionVisible(TestAction::make('tambah')->table());

    Livewire::actingAs($this->pengelolaPmat)
        ->test(AnggotaRelationManager::class, ['ownerRecord' => $this->pbio, 'pageClass' => EditUnit::class])
        ->assertActionHidden(TestAction::make('tambah')->table());
});

it('mengizinkan pengelola membuka halaman lihat unitnya dan mengelola anggota, bukan mengedit atau melihat unit lain', function () {
    $this->actingAs($this->pengelolaPmat)->get("/panel/unit/{$this->pmat->id}")->assertOk()->assertSee('Pendidikan Matematika');
    expect($this->actingAs($this->pengelolaPmat)->get("/panel/unit/{$this->pbio->id}")->getStatusCode())->toBeIn([403, 404]);
    $this->actingAs($this->pengelolaPmat)->get("/panel/unit/{$this->pmat->id}/edit")->assertForbidden();
});
