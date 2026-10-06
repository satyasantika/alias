<?php

use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Filament\Pages\Dasbor;
use App\Filament\Pages\StatistikFakultas;
use App\Filament\Widgets\AntreanModerasiStats;
use App\Filament\Widgets\KlikPerUnitChart;
use App\Filament\Widgets\RekapUnitTable;
use App\Filament\Widgets\RingkasanTautanSaya;
use App\Filament\Widgets\TautanTeratasTable;
use App\Models\KunjunganTautan;
use App\Models\RekapKunjunganHarian;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Support\Analitik\StatistikAgregat;
use Carbon\CarbonImmutable;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Facades\Filament;
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
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->dosen, PeranUnit::Anggota, $this->admin);

    $this->tPmat = TautanPendek::factory()->milikUnit($this->pmat, $this->pengelola)->create(['kode' => 'TopPmat', 'judul' => 'Seminar PMAT']);
    $this->tPbio = TautanPendek::factory()->milikUnit($this->pbio, $this->admin)->create(['kode' => 'TopPbio', 'judul' => 'Seminar PBIO']);
    $this->tDosen = TautanPendek::factory()->milikPribadi($this->dosen)->create(['kode' => 'TopDsn1', 'judul' => 'Tautan dosen']);
    $this->rekap = fn (TautanPendek $t, int $klik, int $hariLalu = 3) => RekapKunjunganHarian::create([
        'tautan_pendek_id' => $t->id, 'tanggal' => now()->subDays($hariLalu)->format('Y-m-d'), 'jumlah_klik' => $klik, 'jumlah_pengunjung_unik' => $klik, 'jumlah_bot' => 0,
    ]);
    ($this->rekap)($this->tPmat, 30);
    ($this->rekap)($this->tPmat, 10, 5);
    ($this->rekap)($this->tPbio, 55);
    ($this->rekap)($this->tDosen, 7);
    ($this->rekap)($this->tPbio, 999, 60);           // di luar periode 30 hari
});

function periode30(): array
{
    $sampai = CarbonImmutable::now()->startOfDay();

    return [$sampai->subDays(29), $sampai];
}

it('menghitung klik per unit untuk admin dan pemantau (semua unit, tautan unit saja)', function () {
    [$dari, $sampai] = periode30();

    foreach ([$this->admin, $this->pemantau] as $pengguna) {
        expect((new StatistikAgregat($pengguna))->klikPerUnit($dari, $sampai)->all())
            ->toBe(['Pendidikan Biologi' => 55, 'Pendidikan Matematika' => 40]);
    }
});

it('membatasi pengelola PMAT pada unitnya: angka unit lain tidak terlihat', function () {
    [$dari, $sampai] = periode30();
    $agregat = new StatistikAgregat($this->pengelola);

    expect($agregat->klikPerUnit($dari, $sampai)->all())->toBe(['Pendidikan Matematika' => 40])
        ->and($agregat->rekapUnit($dari, $sampai)->pluck('unit')->all())->toBe(['Pendidikan Matematika'])
        ->and($agregat->tautanTeratas($dari, $sampai)->pluck('kode')->all())->toBe(['TopPmat'])
        ->and(json_encode($agregat->rekapUnit($dari, $sampai)))->not->toContain('Biologi');
});

it('menghitung rekap unit: tautan aktif, baru, dan klik', function () {
    [$dari, $sampai] = periode30();

    $rekap = (new StatistikAgregat($this->admin))->rekapUnit($dari, $sampai)->keyBy('unit');

    expect($rekap['Pendidikan Matematika'])->toBe(['unit' => 'Pendidikan Matematika', 'aktif' => 1, 'baru' => 1, 'klik' => 40])
        ->and($rekap['Pendidikan Biologi']['klik'])->toBe(55);
});

it('mengurutkan 10 tautan teratas dan hanya memuat kode, judul, unit, klik', function () {
    [$dari, $sampai] = periode30();

    $top = (new StatistikAgregat($this->pemantau))->tautanTeratas($dari, $sampai);

    expect($top->pluck('kode')->all())->toBe(['TopPbio', 'TopPmat', 'TopDsn1'])
        ->and($top[0])->toBe(['kode' => 'TopPbio', 'judul' => 'Seminar PBIO', 'unit' => 'Pendidikan Biologi', 'klik' => 55])
        ->and($top[2]['unit'])->toBe('—')
        ->and(array_keys($top[0]))->toBe(['kode', 'judul', 'unit', 'klik']);
});

it('membatasi pengguna biasa pada tautan yang terlihat olehnya', function () {
    [$dari, $sampai] = periode30();

    $top = (new StatistikAgregat($this->dosen))->tautanTeratas($dari, $sampai);

    expect($top->pluck('kode')->all())->toBe(['TopPmat', 'TopDsn1']);          // unit PMAT (anggota) + pribadi; bukan PBIO
    expect((new StatistikAgregat($this->dosen))->klikTautanSaya(30))->toBe(7);
});

it('menyertakan klik mentah hari ini tanpa bot', function () {
    KunjunganTautan::create(['tautan_pendek_id' => $this->tPmat->id, 'dikunjungi_pada' => now(), 'ip_anonim' => '1.1.1.0', 'ip_hash' => str_repeat('a', 64), 'jenis_perangkat' => 'desktop', 'bot' => false]);
    KunjunganTautan::create(['tautan_pendek_id' => $this->tPmat->id, 'dikunjungi_pada' => now(), 'ip_anonim' => '1.1.1.0', 'ip_hash' => str_repeat('b', 64), 'jenis_perangkat' => 'bot', 'bot' => true]);
    [$dari, $sampai] = periode30();

    expect((new StatistikAgregat($this->admin))->klikPerUnit($dari, $sampai)['Pendidikan Matematika'])->toBe(41);
});

it('menampilkan widget sesuai peran', function () {
    $lihat = fn (User $u, string $w) => (function () use ($u, $w) {
        test()->actingAs($u);

        return $w::canView();
    })();

    expect($lihat($this->dosen, RingkasanTautanSaya::class))->toBeTrue()
        ->and($lihat($this->pengelola, RingkasanTautanSaya::class))->toBeTrue()
        ->and($lihat($this->admin, RingkasanTautanSaya::class))->toBeFalse()
        ->and($lihat($this->pemantau, RingkasanTautanSaya::class))->toBeFalse()
        ->and($lihat($this->dosen, KlikPerUnitChart::class))->toBeFalse()
        ->and($lihat($this->pengelola, KlikPerUnitChart::class))->toBeTrue()
        ->and($lihat($this->pemantau, KlikPerUnitChart::class))->toBeTrue()
        ->and($lihat($this->admin, AntreanModerasiStats::class))->toBeTrue()
        ->and($lihat($this->pemantau, AntreanModerasiStats::class))->toBeFalse()
        ->and($lihat($this->pemantau, TautanTeratasTable::class))->toBeTrue()
        ->and($lihat($this->dosen, TautanTeratasTable::class))->toBeTrue();
});

it('merender widget dengan angka yang benar', function () {
    Livewire::actingAs($this->pengelola)->test(RingkasanTautanSaya::class)->assertSee('Tautan aktif');
    Livewire::actingAs($this->dosen)->test(RingkasanTautanSaya::class)->assertSee('7');
    Livewire::actingAs($this->pemantau)->test(TautanTeratasTable::class)->assertSee('Seminar PBIO')->assertSee('Seminar PMAT');
    Livewire::actingAs($this->pengelola)->test(TautanTeratasTable::class)->assertSee('Seminar PMAT')->assertDontSee('Seminar PBIO');
    Livewire::actingAs($this->admin)->test(RekapUnitTable::class)->assertSee('Pendidikan Biologi')->assertSee('55');
});

it('memberi pemantau agregat tetapi menolak detail tautan dan kunjungan rinci', function () {
    $this->actingAs($this->pemantau)->get('/panel/statistik-fakultas')->assertOk()->assertSee('Statistik fakultas');
    $this->actingAs($this->pemantau)->get('/panel')->assertOk();

    expect($this->actingAs($this->pemantau)->get("/panel/tautan/{$this->tPbio->id}")->getStatusCode())->toBeIn([403, 404])
        ->and($this->actingAs($this->pemantau)->get("/panel/tautan/{$this->tPbio->id}/edit")->getStatusCode())->toBeIn([403, 404]);
    $this->actingAs($this->pemantau)->get('/panel/tautan')->assertForbidden();
    $this->actingAs($this->pemantau)->get('/panel/laporan-penyalahgunaan')->assertForbidden();
    expect($this->pemantau->can('kunjungan.lihat-rinci'))->toBeFalse();
});

it('menolak pengguna biasa membuka statistik fakultas dan memperbolehkan pengelola dan admin', function () {
    $this->actingAs($this->dosen)->get('/panel/statistik-fakultas')->assertForbidden();
    $this->actingAs($this->pengelola)->get('/panel/statistik-fakultas')->assertOk();
    $this->actingAs($this->admin)->get('/panel/statistik-fakultas')->assertOk();
});

it('memuat dasbor untuk setiap peran', function () {
    foreach ([$this->dosen, $this->pengelola, $this->pemantau, $this->admin] as $pengguna) {
        $this->actingAs($pengguna)->get('/panel')->assertOk();
    }

    expect(Dasbor::getUrl())->toContain('/panel')->and(StatistikFakultas::getUrl())->toContain('statistik-fakultas');
});
