<?php

use App\Enums\Peran;
use App\Filament\Imports\PenggunaImporter;
use App\Filament\Resources\PenggunaResource\Pages\ListPengguna;
use App\Models\Import;
use App\Models\User;
use App\Notifications\AturKataSandiAkun;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class]);
    Filament::setCurrentPanel('alias');
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

    $this->impor = function (array $baris, ?User $oleh = null): array {
        $import = new Import;
        $import->user()->associate($oleh ?? $this->admin);
        $import->forceFill(['file_name' => 'uji.csv', 'file_path' => '/tmp/tidak-ada.csv', 'importer' => PenggunaImporter::class, 'total_rows' => count($baris)])->save();

        $kolom = array_combine(array_map(fn ($c) => $c->getName(), PenggunaImporter::getColumns()), array_map(fn ($c) => $c->getName(), PenggunaImporter::getColumns()));
        $berhasil = 0;
        $gagal = [];
        foreach ($baris as $i => $row) {
            try {
                $uji = PenggunaImporter::test($kolom, import: $import)->import($row);
                $uji->assertImported();
                $berhasil++;
            } catch (Throwable $e) {
                $gagal[$i] = $e->getMessage();
            }
        }

        return [$berhasil, $gagal];
    };
});

function barisPengguna(array $beda = []): array
{
    return [
        'name' => 'Dosen Uji', 'email' => 'dosen.uji.'.Str::random(6).'@unsil.ac.id',
        'nip' => '', 'nidn' => '', 'unit_kode' => '', 'kuota_tautan' => '', 'peran' => 'pengguna', ...$beda,
    ];
}

it('mengimpor beberapa pengguna sekaligus dan mengirim surel atur kata sandi', function () {
    Notification::fake();

    [$berhasil, $gagal] = ($this->impor)([
        barisPengguna(['email' => 'dosen.satu@unsil.ac.id']),
        barisPengguna(['email' => 'dosen.dua@unsil.ac.id']),
        barisPengguna(['email' => 'dosen.tiga@unsil.ac.id']),
    ]);

    expect($berhasil)->toBe(3)->and($gagal)->toBeEmpty()
        ->and(User::where('email', 'like', 'dosen.%@unsil.ac.id')->count())->toBe(3);

    $u = User::where('email', 'dosen.satu@unsil.ac.id')->firstOrFail();
    expect($u->hasRole('pengguna'))->toBeTrue()->and($u->password)->toBeNull();
    Notification::assertSentTo($u, AturKataSandiAkun::class);
});

it('menolak surel di luar domain unsil dan surel ganda pada impor massal', function () {
    $ada = User::factory()->create(['email' => 'sudah.ada@unsil.ac.id']);

    [$berhasil, $gagal] = ($this->impor)([
        barisPengguna(['email' => 'x@gmail.com']),
        barisPengguna(['email' => 'sudah.ada@unsil.ac.id']),
        barisPengguna(['email' => 'dosen.valid@unsil.ac.id']),
    ]);

    expect($berhasil)->toBe(1)->and($gagal)->toHaveCount(2);
});

it('mengatur unit homebase dan kuota dari CSV', function () {
    ($this->impor)([barisPengguna(['email' => 'dosen.unit@unsil.ac.id', 'unit_kode' => 'pmat', 'kuota_tautan' => '50'])]);

    $u = User::where('email', 'dosen.unit@unsil.ac.id')->firstOrFail();
    expect($u->unit_id)->not->toBeNull()->and($u->kuota_tautan)->toBe(50);
});

it('menolak kode unit yang tidak ditemukan pada impor massal', function () {
    [$berhasil, $gagal] = ($this->impor)([barisPengguna(['email' => 'dosen.xx@unsil.ac.id', 'unit_kode' => 'TIDAKADA'])]);

    expect($berhasil)->toBe(0)->and($gagal)->toHaveCount(1)->and($gagal[0])->toContain('unit');
});

it('melarang admin-alias memberi peran admin lewat impor massal', function () {
    [$berhasil, $gagal] = ($this->impor)([barisPengguna(['email' => 'coba.admin@unsil.ac.id', 'peran' => 'admin-alias'])]);

    expect($berhasil)->toBe(0)->and($gagal)->toHaveCount(1);
    expect(User::where('email', 'coba.admin@unsil.ac.id')->exists())->toBeFalse();
});

it('menolak impor pengguna oleh peran tanpa pengguna.kelola', function () {
    $pengelola = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);

    [$berhasil] = ($this->impor)([barisPengguna(['email' => 'ditolak@unsil.ac.id'])], $pengelola);

    expect($berhasil)->toBe(0)->and(User::where('email', 'ditolak@unsil.ac.id')->exists())->toBeFalse();
});

it('menyembunyikan aksi impor pengguna dari peran tanpa pengguna.kelola', function () {
    $pengelola = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);

    Livewire::actingAs($pengelola)->test(ListPengguna::class)->assertActionHidden(TestAction::make('import'));
    Livewire::actingAs($this->admin)->test(ListPengguna::class)->assertActionVisible(TestAction::make('import'));
});

it('menyediakan contoh CSV dengan kolom yang benar', function () {
    $nama = array_map(fn ($c) => $c->getName(), PenggunaImporter::getColumns());

    expect($nama)->toBe(['name', 'email', 'nip', 'nidn', 'unit_kode', 'kuota_tautan', 'peran']);
});
