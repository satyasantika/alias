<?php

use App\Actions\Pengguna\NonaktifkanPengguna;
use App\Actions\Tautan\PindahkanKepemilikanTautan;
use App\Actions\Tautan\PindahkanSemuaTautanPengguna;
use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\JenisKepemilikan;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Events\TautanDipindahkan;
use App\Filament\Resources\TautanPendekResource\Pages\ListTautanPendek;
use App\Models\RiwayatKepemilikanTautan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    Filament::setCurrentPanel('alias');
    $this->pmat = Unit::where('kode', 'PMAT')->first();
    $this->pbio = Unit::create(['kode' => 'PBIO', 'nama' => 'Pendidikan Biologi', 'jenis' => 'prodi']);
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->pengelola = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pengelola, PeranUnit::Pengelola, $this->admin);
    $this->dosen = User::factory()->create()->assignRole(Peran::Pengguna->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->dosen, PeranUnit::Anggota, $this->pengelola);
    $this->luar = User::factory()->create()->assignRole(Peran::Pengguna->value);
});

it('memindahkan tautan pribadi ke unit tempat pemilik menjadi anggota tanpa mengubah kode/tujuan/status/statistik', function () {
    Event::fake([TautanDipindahkan::class]);
    $t = TautanPendek::factory()->milikPribadi($this->dosen)->create(['kode' => 'Tetap123']);
    $t->forceFill(['jumlah_klik' => 42])->saveQuietly();
    $url = $t->url_tujuan;

    app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->pmat, $this->dosen, 'Pindah ke prodi');

    $t->refresh();
    expect($t->jenis_kepemilikan)->toBe(JenisKepemilikan::Unit)
        ->and($t->unit_id)->toBe($this->pmat->id)->and($t->pemilik_id)->toBeNull()
        ->and($t->kode)->toBe('Tetap123')->and($t->url_tujuan)->toBe($url)->and($t->jumlah_klik)->toBe(42)
        ->and($t->dibuat_oleh)->toBe($this->dosen->id);

    $r = RiwayatKepemilikanTautan::first();
    expect($r->dari_jenis)->toBe(JenisKepemilikan::Pribadi)->and($r->dari_pemilik_id)->toBe($this->dosen->id)
        ->and($r->ke_unit_id)->toBe($this->pmat->id)->and($r->oleh)->toBe($this->dosen->id)->and($r->alasan)->toBe('Pindah ke prodi');
    Event::assertDispatched(TautanDipindahkan::class);
});

it('menolak pengguna memindahkan ke unit lain atau ke pengguna lain', function () {
    $t = TautanPendek::factory()->milikPribadi($this->dosen)->create();

    expect(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->pbio, $this->dosen))->toThrow(ValidationException::class)
        ->and(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->luar, $this->dosen))->toThrow(ValidationException::class);
});

it('menolak orang yang bukan pemilik memulai pemindahan', function () {
    $t = TautanPendek::factory()->milikPribadi($this->luar)->create();

    expect(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->pmat, $this->dosen))->toThrow(AuthorizationException::class);
});

it('mengizinkan pengelola memindahkan tautan unit ke pribadi anggota unit dan sebaliknya', function () {
    $t = TautanPendek::factory()->milikUnit($this->pmat, $this->pengelola)->create();

    app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->dosen, $this->pengelola);
    expect($t->fresh()->pemilik_id)->toBe($this->dosen->id)->and($t->fresh()->unit_id)->toBeNull();

    app(PindahkanKepemilikanTautan::class)->jalankan($t->fresh(), $this->pmat, $this->pengelola, 'Kembali ke unit');
    expect($t->fresh()->unit_id)->toBe($this->pmat->id);
});

it('menolak pengelola memindahkan ke bukan-anggota atau tautan pribadi non-anggota', function () {
    $unit = TautanPendek::factory()->milikUnit($this->pmat, $this->pengelola)->create();
    $pribadiLuar = TautanPendek::factory()->milikPribadi($this->luar)->create();

    expect(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($unit, $this->luar, $this->pengelola))->toThrow(ValidationException::class)
        ->and(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($pribadiLuar, $this->pmat, $this->pengelola))->toThrow(AuthorizationException::class);
});

it('mengizinkan pengelola memindahkan tautan pribadi anggota unitnya ke unitnya', function () {
    $t = TautanPendek::factory()->milikPribadi($this->dosen)->create();

    app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->pmat, $this->pengelola, 'Dosen pindah tugas');

    expect($t->fresh()->unit_id)->toBe($this->pmat->id);
});

it('mengizinkan admin memindahkan ke tujuan mana pun tetapi menolak tujuan sama atau nonaktif', function () {
    $t = TautanPendek::factory()->milikPribadi($this->dosen)->create();

    expect(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->dosen, $this->admin))->toThrow(ValidationException::class, 'sama');

    $nonaktif = User::factory()->create(['aktif' => false]);
    expect(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($t, $nonaktif, $this->admin))->toThrow(ValidationException::class, 'tidak aktif');

    app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->pbio, $this->admin);
    expect($t->fresh()->unit_id)->toBe($this->pbio->id);
});

it('menegakkan kuota tujuan kecuali oleh pemegang tanpa-kuota', function () {
    $this->pmat->update(['kuota_tautan' => 1]);
    TautanPendek::factory()->milikUnit($this->pmat, $this->pengelola)->create();
    $t = TautanPendek::factory()->milikPribadi($this->dosen)->create();

    expect(fn () => app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->pmat, $this->dosen))->toThrow(ValidationException::class, 'Kuota');

    app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->pmat, $this->admin);
    expect($t->fresh()->unit_id)->toBe($this->pmat->id);
});

it('menyediakan hanya tujuan sah untuk antarmuka', function () {
    $t = TautanPendek::factory()->milikPribadi($this->dosen)->create();

    $dosen = app(PindahkanKepemilikanTautan::class)->tujuanSah($t, $this->dosen);
    expect(array_keys($dosen['unit']))->toBe([$this->pmat->id])->and($dosen['pengguna'])->toBe([]);

    $admin = app(PindahkanKepemilikanTautan::class)->tujuanSah($t, $this->admin);
    expect($admin['unit'])->toHaveCount(3)->and(array_keys($admin['pengguna']))->not->toContain($this->dosen->id);
});

it('memindahkan semua tautan pengguna secara massal lintas kelompok 200', function () {
    TautanPendek::factory()->count(205)->milikPribadi($this->dosen)->create();
    TautanPendek::factory()->count(2)->milikPribadi($this->luar)->create();

    $jumlah = app(PindahkanSemuaTautanPengguna::class)->jalankan($this->dosen, $this->pmat, $this->admin);

    expect($jumlah)->toBe(205)
        ->and(TautanPendek::where('pemilik_id', $this->dosen->id)->count())->toBe(0)
        ->and(TautanPendek::where('unit_id', $this->pmat->id)->count())->toBe(205)
        ->and(TautanPendek::where('pemilik_id', $this->luar->id)->count())->toBe(2)
        ->and(RiwayatKepemilikanTautan::count())->toBe(205);
});

it('menolak pemindahan massal oleh non-admin dan saat lock aktif', function () {
    TautanPendek::factory()->count(2)->milikPribadi($this->dosen)->create();

    expect(fn () => app(PindahkanSemuaTautanPengguna::class)->jalankan($this->dosen, $this->pmat, $this->pengelola))->toThrow(HttpException::class);

    $kunci = Cache::lock('pindah-tautan-massal:'.$this->dosen->id, 120);
    expect($kunci->get())->toBeTrue();
    expect(fn () => app(PindahkanSemuaTautanPengguna::class)->jalankan($this->dosen, $this->pmat, $this->admin))->toThrow(ValidationException::class, 'sedang berjalan');
    $kunci->release();

    expect(app(PindahkanSemuaTautanPengguna::class)->jalankan($this->dosen, $this->pmat, $this->admin))->toBe(2);
});

it('memindahkan semua tautan saat menonaktifkan pengguna dengan pengalihan', function () {
    TautanPendek::factory()->count(3)->milikPribadi($this->dosen)->create();

    app(NonaktifkanPengguna::class)->jalankan($this->dosen, $this->admin, $this->pmat);

    expect($this->dosen->fresh()->aktif)->toBeFalse()
        ->and(TautanPendek::where('unit_id', $this->pmat->id)->count())->toBe(3)
        ->and($this->dosen->fresh()->jumlahTautanAktif())->toBe(0);
});

it('memindahkan lewat aksi tabel dan aksi massal admin', function () {
    $t = TautanPendek::factory()->milikPribadi($this->dosen)->create();
    $t2 = TautanPendek::factory()->milikPribadi($this->dosen)->create();

    Livewire::actingAs($this->dosen)->test(ListTautanPendek::class)
        ->callAction(TestAction::make('pindahkan')->table($t), ['unit_tujuan' => $this->pmat->id, 'alasan' => 'Ke prodi']);
    expect($t->fresh()->unit_id)->toBe($this->pmat->id);

    Livewire::actingAs($this->admin)->test(ListTautanPendek::class)
        ->callAction(TestAction::make('pindahkan_terpilih')->table()->bulk(), data: ['pengguna_tujuan' => $this->luar->id], arguments: [])
        ->assertNotified();
});
