<?php

use App\Enums\Peran;
use App\Enums\StatusTautan;
use App\Filament\Imports\TautanImporter;
use App\Filament\Resources\TautanPendekResource\Pages\ListTautanPendek;
use App\Jobs\PeriksaKesehatanTujuan;
use App\Models\Import;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tujuan\ResolverDns;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Actions\Imports\Events\ImportCompleted;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class, SlugTerlarangSeeder::class, AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    Queue::fake([PeriksaKesehatanTujuan::class]);
    Filament::setCurrentPanel('alias');
    app()->instance(ResolverDns::class, new class extends ResolverDns
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->pmat = Unit::where('kode', 'PMAT')->first();
    $this->dosen = User::factory()->create(['email' => 'dosen.a@unsil.ac.id'])->assignRole(Peran::Pengguna->value);

    $this->impor = function (array $baris, ?User $oleh = null): array {
        $import = new Import;
        $import->user()->associate($oleh ?? $this->admin);
        $import->forceFill(['file_name' => 'uji.csv', 'file_path' => '/tmp/tidak-ada.csv', 'importer' => TautanImporter::class, 'total_rows' => count($baris)])->save();

        $kolom = array_combine(array_map(fn ($c) => $c->getName(), TautanImporter::getColumns()), array_map(fn ($c) => $c->getName(), TautanImporter::getColumns()));
        $berhasil = 0;
        $gagal = [];
        foreach ($baris as $i => $row) {
            try {
                $uji = TautanImporter::test($kolom, import: $import)->import($row);
                $uji->assertImported();
                $berhasil++;
            } catch (Throwable $e) {
                $gagal[$i] = $e->getMessage();
            }
        }

        return [$berhasil, $gagal];
    };
});

function barisCsv(array $beda = []): array
{
    return [
        'judul' => 'Tautan', 'url_tujuan' => 'https://forms.gle/'.Str::random(6), 'slug' => '', 'jenis_kepemilikan' => 'pribadi',
        'email_pemilik' => 'dosen.a@unsil.ac.id', 'kode_unit' => '', 'aktif_sampai' => '', ...$beda,
    ];
}

it('mengimpor 10 baris: 7 berhasil dan 3 gagal dengan alasan (slug bentrok, tujuan javascript:)', function () {
    $baris = [
        barisCsv(['slug' => 'semi-1']),
        barisCsv(['slug' => 'semi-2']),
        barisCsv(['slug' => 'semi-1']),            // bentrok dengan baris 1
        barisCsv(['slug' => 'panel']),             // dicadangkan sistem
        barisCsv(['url_tujuan' => 'javascript:alert(1)']),
        barisCsv(['jenis_kepemilikan' => 'unit', 'kode_unit' => 'PMAT', 'email_pemilik' => '']),
        barisCsv(),
        barisCsv(['aktif_sampai' => '2030-01-01 10:00']),
        barisCsv(['judul' => 'Judul lain']),
        barisCsv(['slug' => 'semi-9']),
    ];

    [$berhasil, $gagal] = ($this->impor)($baris);

    expect($berhasil)->toBe(7)->and($gagal)->toHaveCount(3);
    expect(array_keys($gagal))->toBe([2, 3, 4]);
    expect($gagal[2])->toContain('sudah dipakai')->and($gagal[3])->toContain('dicadangkan')->and($gagal[4])->toContain('http');
    expect(TautanPendek::count())->toBe(7);
});

it('mengatur pemilik pribadi ke surel pada CSV dan pembuat ke pengimpor, aktif tanpa persetujuan', function () {
    ($this->impor)([barisCsv(['slug' => 'impor-slug'])]);

    $t = TautanPendek::where('kode', 'impor-slug')->firstOrFail();
    expect($t->pemilik_id)->toBe($this->dosen->id)->and($t->dibuat_oleh)->toBe($this->admin->id)->and($t->status)->toBe(StatusTautan::Aktif);
});

it('mengimpor tautan unit dan tanggal kedaluwarsa', function () {
    ($this->impor)([barisCsv(['jenis_kepemilikan' => 'unit', 'kode_unit' => 'pmat', 'email_pemilik' => '', 'aktif_sampai' => '2030-05-01 08:00'])]);

    $t = TautanPendek::firstOrFail();
    expect($t->unit_id)->toBe($this->pmat->id)->and($t->pemilik_id)->toBeNull()->and($t->aktif_sampai->format('Y-m-d H:i'))->toBe('2030-05-01 08:00');
});

it('menolak pemilik atau unit yang tidak ditemukan dengan alasan jelas', function () {
    [, $gagal] = ($this->impor)([
        barisCsv(['email_pemilik' => 'tidak.ada@unsil.ac.id']),
        barisCsv(['jenis_kepemilikan' => 'unit', 'kode_unit' => 'XXX', 'email_pemilik' => '']),
    ]);

    expect($gagal[0])->toContain('Pemilik')->and($gagal[1])->toContain('unit');
});

it('mengabaikan kuota dan batas laju saat impor', function () {
    $this->dosen->forceFill(['kuota_tautan' => 1])->save();

    $baris = array_map(fn ($i) => barisCsv(), range(1, 35));
    [$berhasil] = ($this->impor)($baris);

    expect($berhasil)->toBe(35)->and(TautanPendek::where('pemilik_id', $this->dosen->id)->count())->toBe(35);
});

it('menolak impor oleh pengguna tanpa tautan.impor', function () {
    $pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);

    [$berhasil, $gagal] = ($this->impor)([barisCsv()], $pengguna);

    expect($berhasil)->toBe(0)->and(TautanPendek::count())->toBe(0);
});

it('menyembunyikan aksi impor dari pengguna tanpa tautan.impor', function () {
    $pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);

    Livewire::actingAs($pengguna)->test(ListTautanPendek::class)->assertActionHidden(TestAction::make('import'));
    Livewire::actingAs($this->admin)->test(ListTautanPendek::class)->assertActionVisible(TestAction::make('import'));
});

it('menghapus berkas CSV setelah impor selesai', function () {
    $berkas = storage_path('app/tmp/impor-uji.csv');
    File::ensureDirectoryExists(dirname($berkas));
    File::put($berkas, "judul,url_tujuan\nA,https://forms.gle/a\n");

    $import = new Import;
    $import->user()->associate($this->admin);
    $import->forceFill(['file_name' => 'uji.csv', 'file_path' => $berkas, 'importer' => TautanImporter::class, 'total_rows' => 1])->save();

    event(new ImportCompleted($import, [], []));

    expect(File::exists($berkas))->toBeFalse();
});

it('memakai kunci UUID untuk tabel impor', function () {
    $import = new Import;
    $import->user()->associate($this->admin);
    $import->forceFill(['file_name' => 'x.csv', 'file_path' => '/tmp/x', 'importer' => TautanImporter::class, 'total_rows' => 0])->save();

    expect(Str::isUuid($import->getKey()))->toBeTrue()->and(app(\Filament\Actions\Imports\Models\Import::class))->toBeInstanceOf(Import::class);
});

it('menyediakan contoh CSV dengan kolom yang benar', function () {
    $nama = array_map(fn ($c) => $c->getName(), TautanImporter::getColumns());

    expect($nama)->toBe(['judul', 'url_tujuan', 'slug', 'jenis_kepemilikan', 'email_pemilik', 'kode_unit', 'aktif_sampai']);
});
