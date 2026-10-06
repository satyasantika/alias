<?php

use App\Enums\DimensiRekap;
use App\Enums\Peran;
use App\Filament\Resources\TautanPendekResource\Pages\ViewTautanPendek;
use App\Filament\Resources\TautanPendekResource\RelationManagers\KunjunganRelationManager;
use App\Filament\Widgets\Tautan\KlikHarianChart;
use App\Filament\Widgets\Tautan\PerangkatChart;
use App\Filament\Widgets\Tautan\PerujukTeratasTable;
use App\Models\KunjunganTautan;
use App\Models\RekapKunjunganDimensi;
use App\Models\RekapKunjunganHarian;
use App\Models\TautanPendek;
use App\Models\User;
use App\Support\Analitik\StatistikTautan;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('alias');
    $this->pemilik = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->tautan = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Stat001']);
    $this->rekap = fn (int $hariLalu, int $klik, int $unik, int $bot = 0) => RekapKunjunganHarian::create([
        'tautan_pendek_id' => $this->tautan->id, 'tanggal' => now()->subDays($hariLalu)->format('Y-m-d'),
        'jumlah_klik' => $klik, 'jumlah_pengunjung_unik' => $unik, 'jumlah_bot' => $bot,
    ]);
    $this->dimensi = fn (int $hariLalu, string $dim, string $nilai, int $jumlah) => RekapKunjunganDimensi::create([
        'tautan_pendek_id' => $this->tautan->id, 'tanggal' => now()->subDays($hariLalu)->format('Y-m-d'), 'dimensi' => $dim, 'nilai' => $nilai, 'jumlah' => $jumlah,
    ]);
    $this->mentah = fn (array $a = []) => KunjunganTautan::create([
        'tautan_pendek_id' => $this->tautan->id, 'dikunjungi_pada' => now()->setTime(8, 0), 'ip_anonim' => '103.21.44.0', 'ip_hash' => str_repeat('a', 64),
        'peramban' => 'Chrome', 'os' => 'Windows', 'jenis_perangkat' => 'desktop', 'bot' => false, ...$a,
    ]);
});

it('menghitung klik harian dari rekap sama dengan perhitungan manual, tanpa lubang tanggal', function () {
    ($this->rekap)(2, 10, 7);
    ($this->rekap)(5, 4, 4, 3);
    ($this->rekap)(40, 99, 99);                       // di luar jendela 30 hari

    $h = (new StatistikTautan($this->tautan))->klikHarian(30);

    expect($h)->toHaveCount(30)
        ->and($h[now()->subDays(2)->format('Y-m-d')])->toBe(['klik' => 10, 'unik' => 7, 'bot' => 0])
        ->and($h[now()->subDays(5)->format('Y-m-d')])->toBe(['klik' => 4, 'unik' => 4, 'bot' => 3])
        ->and($h[now()->subDays(1)->format('Y-m-d')])->toBe(['klik' => 0, 'unik' => 0, 'bot' => 0])
        ->and((new StatistikTautan($this->tautan))->total(30))->toBe(['klik' => 14, 'unik' => 11, 'bot' => 3])
        ->and((new StatistikTautan($this->tautan))->total(90)['klik'])->toBe(113);
});

it('menambahkan data mentah hari ini dan kemarin bila belum direkap, bot tidak dihitung', function () {
    ($this->mentah)();
    ($this->mentah)(['ip_hash' => str_repeat('b', 64)]);
    ($this->mentah)(['ip_hash' => str_repeat('a', 64)]);          // unik tetap 2
    ($this->mentah)(['bot' => true, 'ip_hash' => str_repeat('c', 64)]);
    ($this->mentah)(['dikunjungi_pada' => now()->subDay()->setTime(9, 0)]);   // kemarin belum direkap
    ($this->rekap)(3, 5, 5);

    $h = (new StatistikTautan($this->tautan))->klikHarian(30);

    expect($h[now()->format('Y-m-d')])->toBe(['klik' => 3, 'unik' => 2, 'bot' => 1])
        ->and($h[now()->subDay()->format('Y-m-d')])->toBe(['klik' => 1, 'unik' => 1, 'bot' => 0])
        ->and($h->sum('klik'))->toBe(9);
});

it('tidak menghitung dua kali kemarin bila sudah direkap', function () {
    ($this->mentah)(['dikunjungi_pada' => now()->subDay()->setTime(9, 0)]);
    ($this->rekap)(1, 1, 1);

    expect((new StatistikTautan($this->tautan))->klikHarian(7)[now()->subDay()->format('Y-m-d')]['klik'])->toBe(1);
});

it('menjumlah dimensi dari rekap dan data mentah hari ini, terurut menurun dan terbatas', function () {
    ($this->dimensi)(2, 'peramban', 'Chrome', 6);
    ($this->dimensi)(3, 'peramban', 'Firefox', 2);
    ($this->dimensi)(4, 'peramban', 'Chrome', 4);
    ($this->dimensi)(50, 'peramban', 'Edge', 100);          // luar jendela
    ($this->rekap)(2, 6, 6);
    ($this->mentah)(['peramban' => 'Safari']);
    ($this->mentah)(['peramban' => 'Chrome']);
    ($this->mentah)(['peramban' => 'Opera', 'bot' => true]);

    $d = (new StatistikTautan($this->tautan))->dimensi(DimensiRekap::Peramban, 30);

    expect($d->all())->toBe(['Chrome' => 11, 'Firefox' => 2, 'Safari' => 1])
        ->and((new StatistikTautan($this->tautan))->dimensi(DimensiRekap::Peramban, 30, 2)->keys()->all())->toBe(['Chrome', 'Firefox']);
});

it('memberi label (langsung) untuk perujuk kosong pada data mentah', function () {
    ($this->mentah)(['perujuk_host' => null]);
    ($this->mentah)(['perujuk_host' => 'wa.me']);
    ($this->mentah)(['perujuk_host' => null]);

    expect((new StatistikTautan($this->tautan))->dimensi(DimensiRekap::PerujukHost, 30)->all())->toBe(['(langsung)' => 2, 'wa.me' => 1]);
});

it('menampilkan angka widget sama dengan data', function () {
    ($this->rekap)(2, 10, 7);
    ($this->dimensi)(2, 'jenis_perangkat', 'desktop', 8);
    ($this->dimensi)(2, 'jenis_perangkat', 'ponsel', 2);
    ($this->dimensi)(2, 'perujuk_host', 'www.facebook.com', 5);

    $chart = Livewire::actingAs($this->pemilik)->test(KlikHarianChart::class, ['record' => $this->tautan]);
    $data = (fn () => $this->getData())->call($chart->instance());
    expect($data['datasets'][0]['data'])->toContain(10);
    $chart->assertSee('per hari');

    $perangkat = Livewire::actingAs($this->pemilik)->test(PerangkatChart::class, ['record' => $this->tautan]);
    expect((fn () => $this->getData())->call($perangkat->instance())['labels'])->toBe(['desktop', 'ponsel']);

    Livewire::actingAs($this->pemilik)->test(PerujukTeratasTable::class, ['record' => $this->tautan])->assertSee('www.facebook.com');
});

it('menampilkan halaman statistik hanya untuk yang berhak (pemilik lain tidak menemukannya)', function () {
    $lain = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $pemantau = User::factory()->create()->assignRole(Peran::Pemantau->value);

    $this->actingAs($this->pemilik)->get("/panel/tautan/{$this->tautan->id}")->assertOk()->assertSee('Stat001');
    $this->actingAs($lain)->get("/panel/tautan/{$this->tautan->id}")->assertNotFound();
    expect($this->actingAs($pemantau)->get("/panel/tautan/{$this->tautan->id}")->getStatusCode())->toBeIn([403, 404]);
});

it('membatasi relation manager kunjungan pada pemegang kunjungan.lihat-rinci tanpa kolom sensitif', function () {
    ($this->mentah)();
    $pemantau = User::factory()->create()->assignRole(Peran::Pemantau->value);
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);

    expect(KunjunganRelationManager::canViewForRecord($this->tautan, ViewTautanPendek::class))->toBeFalse();   // tanpa login

    $this->actingAs($this->pemilik);
    expect(KunjunganRelationManager::canViewForRecord($this->tautan, ViewTautanPendek::class))->toBeTrue();
    $this->actingAs($pemantau);
    expect(KunjunganRelationManager::canViewForRecord($this->tautan, ViewTautanPendek::class))->toBeFalse();
    $this->actingAs($admin);
    expect(KunjunganRelationManager::canViewForRecord($this->tautan, ViewTautanPendek::class))->toBeTrue();

    $html = Livewire::actingAs($this->pemilik)->test(KunjunganRelationManager::class, ['ownerRecord' => $this->tautan, 'pageClass' => ViewTautanPendek::class])
        ->assertCanSeeTableRecords($this->tautan->kunjungan)->html();
    expect($html)->toContain('103.21.44.0')->not->toContain(str_repeat('a', 64))->not->toContain('user_agent');
});
