<?php

use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Filament\Exports\KunjunganExporter;
use App\Filament\Exports\LaporanModerasiExporter;
use App\Filament\Exports\RekapTautanExporter;
use App\Filament\Exports\RekapUnitExporter;
use App\Filament\Pages\StatistikFakultas;
use App\Filament\Resources\LaporanPenyalahgunaanResource\Pages\ListLaporanPenyalahgunaan;
use App\Filament\Resources\TautanPendekResource\Pages\ListTautanPendek;
use App\Filament\Resources\TautanPendekResource\Pages\ViewTautanPendek;
use App\Filament\Resources\TautanPendekResource\RelationManagers\KunjunganRelationManager;
use App\Models\Export;
use App\Models\KunjunganTautan;
use App\Models\LaporanPenyalahgunaan;
use App\Models\RekapKunjunganHarian;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class, PengaturanSeeder::class]);
    Filament::setCurrentPanel('alias');
    $this->pmat = Unit::where('kode', 'PMAT')->first();
    $this->pbio = Unit::create(['kode' => 'PBIO', 'nama' => 'Pendidikan Biologi', 'jenis' => 'prodi']);
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->pengelola = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pengelola, PeranUnit::Pengelola, $this->admin);
    $this->pemantau = User::factory()->create()->assignRole(Peran::Pemantau->value);
    $this->dosen = User::factory()->create()->assignRole(Peran::Pengguna->value);

    $this->tPmat = TautanPendek::factory()->milikUnit($this->pmat, $this->pengelola)->create(['kode' => 'ExpPmat']);
    $this->tPbio = TautanPendek::factory()->milikUnit($this->pbio, $this->admin)->create(['kode' => 'ExpPbio']);
    $this->tDosen = TautanPendek::factory()->milikPribadi($this->dosen)->create(['kode' => 'ExpDsn1']);
    foreach ([$this->tPmat, $this->tPbio] as $t) {
        RekapKunjunganHarian::create(['tautan_pendek_id' => $t->id, 'tanggal' => now()->subDays(2)->format('Y-m-d'), 'jumlah_klik' => 5, 'jumlah_pengunjung_unik' => 5, 'jumlah_bot' => 0]);
        KunjunganTautan::create(['tautan_pendek_id' => $t->id, 'dikunjungi_pada' => now()->subDay(), 'ip_anonim' => '103.21.44.0', 'ip_hash' => str_repeat('f', 64), 'peramban' => 'Chrome', 'jenis_perangkat' => 'desktop', 'bot' => false]);
    }
    File::deleteDirectory(storage_path('app/tmp/filament_exports'));
});

afterEach(fn () => File::deleteDirectory(storage_path('app/tmp/filament_exports')));

function barisEkspor(string $exporter, User $oleh, array $opsi = []): array
{
    test()->actingAs($oleh);
    $export = new Export;
    $export->user()->associate($oleh);
    $export->forceFill(['file_disk' => 'tmp', 'exporter' => $exporter, 'total_rows' => 0])->save();

    $kolom = [];
    foreach ($exporter::getColumns() as $k) {
        $kolom[$k->getName()] = $k->getName();
    }
    $instance = $export->getExporter($kolom, $opsi);

    return $exporter::modifyQuery($exporter::getModel()::query())->get()
        ->map(fn ($r) => $instance($r))->all();
}

it('membatasi ekspor rekap tautan pada cakupan peran', function () {
    $kode = fn (User $u) => collect(barisEkspor(RekapTautanExporter::class, $u))->pluck(0)->sort()->values()->all();

    expect($kode($this->admin))->toBe(['ExpDsn1', 'ExpPbio', 'ExpPmat'])
        ->and($kode($this->pengelola))->toBe(['ExpPmat'])
        ->and($kode($this->dosen))->toBe(['ExpDsn1'])
        ->and($kode($this->pemantau))->toBe([]);
});

it('tidak memuat kata sandi, ip_hash, atau surel pelapor pada ekspor apa pun', function () {
    $this->tDosen->forceFill(['kata_sandi_hash' => 'HASH-RAHASIA-123'])->saveQuietly();
    LaporanPenyalahgunaan::create(['tautan_pendek_id' => $this->tPmat->id, 'kode_dilaporkan' => 'ExpPmat', 'kategori' => 'spam', 'ip_hash' => str_repeat('e', 64), 'email_pelapor' => 'pelapor@contoh.com']);

    $semua = json_encode([
        barisEkspor(RekapTautanExporter::class, $this->admin),
        barisEkspor(KunjunganExporter::class, $this->admin),
        barisEkspor(RekapUnitExporter::class, $this->admin),
        barisEkspor(LaporanModerasiExporter::class, $this->admin),
    ]);

    expect($semua)->not->toContain('HASH-RAHASIA-123')->not->toContain(str_repeat('f', 64))->not->toContain(str_repeat('e', 64))->not->toContain('pelapor@contoh.com')
        ->and($semua)->toContain('103.21.44.0');
    foreach ([RekapTautanExporter::class, KunjunganExporter::class, RekapUnitExporter::class, LaporanModerasiExporter::class] as $kelas) {
        expect(collect($kelas::getColumns())->map->getName()->implode(','))->not->toContain('ip_hash')->not->toContain('kata_sandi')->not->toContain('user_agent');
    }
});

it('membatasi ekspor kunjungan pada pemegang kunjungan.lihat-rinci dan tautan yang terlihat', function () {
    $kode = fn (User $u) => collect(barisEkspor(KunjunganExporter::class, $u))->pluck(0)->sort()->values()->all();

    expect($kode($this->admin))->toBe(['ExpPbio', 'ExpPmat'])
        ->and($kode($this->pengelola))->toBe(['ExpPmat'])
        ->and($kode($this->dosen))->toBe([])
        ->and($kode($this->pemantau))->toBe([]);
});

it('membatasi ekspor rekap unit: pengelola hanya unitnya, pemantau semua agregat', function () {
    $unit = fn (User $u) => collect(barisEkspor(RekapUnitExporter::class, $u))->pluck(1)->sort()->values()->all();

    expect($unit($this->pengelola))->toBe(['Pendidikan Matematika'])
        ->and($unit($this->pemantau))->toBe(['Fakultas Keguruan dan Ilmu Pendidikan', 'Pendidikan Biologi', 'Pendidikan Matematika'])
        ->and($unit($this->dosen))->toBe([]);

    $baris = collect(barisEkspor(RekapUnitExporter::class, $this->admin))->keyBy(1);
    expect((int) $baris['Pendidikan Matematika'][3])->toBe(1)->and((int) $baris['Pendidikan Matematika'][5])->toBe(5);
});

it('membatasi rekap moderasi pada admin', function () {
    LaporanPenyalahgunaan::create(['kode_dilaporkan' => 'X', 'kategori' => 'spam', 'ip_hash' => str_repeat('e', 64)]);

    expect(barisEkspor(LaporanModerasiExporter::class, $this->admin))->toHaveCount(1)
        ->and(barisEkspor(LaporanModerasiExporter::class, $this->dosen))->toBe([])
        ->and(barisEkspor(LaporanModerasiExporter::class, $this->pengelola))->toBe([]);
    expect((new LaporanModerasiExporter(new Export, [], []))->getFormats())->toBe([ExportFormat::Xlsx]);
});

it('menyimpan berkas ekspor di disk tmp (bukan publik) dan membuat berkas CSV lewat aksi panel', function () {
    Livewire::actingAs($this->pengelola)->test(ListTautanPendek::class)
        ->callAction('export', data: ['columnMap' => collect(RekapTautanExporter::getColumns())->mapWithKeys(fn ($k) => [$k->getName() => ['isEnabled' => true, 'label' => $k->getLabel()]])->all()])
        ->assertHasNoActionErrors();

    $export = Export::firstOrFail();
    expect($export->file_disk)->toBe('tmp')->and(Str::isUuid($export->getKey()))->toBeTrue();

    $csv = collect(File::allFiles(storage_path('app/tmp/filament_exports')))->first(fn ($f) => str_ends_with($f->getFilename(), '.csv') && ! str_contains($f->getFilename(), 'headers'));
    expect($csv)->not->toBeNull();
    $isi = File::get($csv->getPathname());
    expect($isi)->toContain('ExpPmat')->not->toContain('ExpPbio')->not->toContain('ExpDsn1');
    expect(array_values(array_diff(Storage::disk('public')->allFiles(), ['.gitignore'])))->toBe([]);
});

it('menyembunyikan aksi ekspor dari pengguna yang tidak berhak', function () {
    Livewire::actingAs($this->pemantau)->test(StatistikFakultas::class)->assertActionVisible(TestAction::make('export'));
    Livewire::actingAs($this->admin)->test(ListLaporanPenyalahgunaan::class)->assertActionVisible(TestAction::make('export')->table());
    Livewire::actingAs($this->admin)->test(KunjunganRelationManager::class, ['ownerRecord' => $this->tPmat, 'pageClass' => ViewTautanPendek::class])
        ->assertActionVisible(TestAction::make('export')->table());
});

it('menempatkan berkas ekspor pada folder yang dibersihkan otomatis setelah 24 jam', function () {
    File::ensureDirectoryExists(storage_path('app/tmp/filament_exports/uji'));
    File::put(storage_path('app/tmp/filament_exports/uji/lama.csv'), 'x');
    touch(storage_path('app/tmp/filament_exports/uji/lama.csv'), now()->subHours(30)->getTimestamp());

    $this->artisan('alias:bersihkan-tmp')->assertSuccessful();

    expect(File::exists(storage_path('app/tmp/filament_exports/uji/lama.csv')))->toBeFalse();
});
