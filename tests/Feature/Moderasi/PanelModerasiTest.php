<?php

use App\Actions\Moderasi\TanganiLaporan;
use App\Enums\Peran;
use App\Enums\StatusLaporan;
use App\Enums\StatusTautan;
use App\Filament\Resources\LaporanPenyalahgunaanResource\Pages\ListLaporanPenyalahgunaan;
use App\Filament\Widgets\AntreanModerasiStats;
use App\Models\LaporanPenyalahgunaan;
use App\Models\TautanPendek;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('alias');
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->tautan = TautanPendek::factory()->milikPribadi($this->pengguna)->create(['kode' => 'Mod0001']);
    $this->laporan = fn (array $a = []) => LaporanPenyalahgunaan::create([
        'tautan_pendek_id' => $this->tautan->id, 'kode_dilaporkan' => 'Mod0001', 'kategori' => 'phishing', 'ip_hash' => str_repeat('a', 64), ...$a,
    ]);
});

it('memblokir dari laporan: tautan 410 dan semua laporan terkait ditindaklanjuti', function () {
    $a = ($this->laporan)(['ip_hash' => str_repeat('a', 64)]);
    $b = ($this->laporan)(['ip_hash' => str_repeat('b', 64), 'kategori' => 'judi']);
    $lain = LaporanPenyalahgunaan::create(['kode_dilaporkan' => 'X', 'kategori' => 'spam', 'ip_hash' => str_repeat('c', 64)]);

    app(TanganiLaporan::class)->blokirTautan($a, 'Mengarah ke situs penipuan bank.', $this->admin);

    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Diblokir)
        ->and($a->fresh()->status)->toBe(StatusLaporan::Ditindaklanjuti)->and($a->fresh()->tindakan)->toBe('blokir')
        ->and($b->fresh()->status)->toBe(StatusLaporan::Ditindaklanjuti)
        ->and($lain->fresh()->status)->toBe(StatusLaporan::Baru)
        ->and($a->fresh()->ditangani_oleh)->toBe($this->admin->id);
    $this->get('/Mod0001')->assertStatus(410);
});

it('menolak blokir tanpa alasan memadai dan oleh non-moderator', function () {
    $l = ($this->laporan)();

    expect(fn () => app(TanganiLaporan::class)->blokirTautan($l, 'pendek', $this->admin))->toThrow(ValidationException::class)
        ->and(fn () => app(TanganiLaporan::class)->blokirTautan($l, 'Alasan yang cukup panjang.', $this->pengguna))->toThrow(AuthorizationException::class);
    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Aktif);
});

it('menolak laporan hanya dengan alasan', function () {
    $l = ($this->laporan)();

    expect(fn () => app(TanganiLaporan::class)->tolak($l, '  ', $this->admin))->toThrow(ValidationException::class);

    app(TanganiLaporan::class)->tolak($l, 'Tautan sah, tidak terbukti menyalahgunakan.', $this->admin);
    expect($l->fresh()->status)->toBe(StatusLaporan::Ditolak)->and($this->tautan->fresh()->status)->toBe(StatusTautan::Aktif);
});

it('menjalankan alur baru → ditinjau → ditindaklanjuti dan tidak mengubah laporan yang selesai', function () {
    $l = ($this->laporan)();

    app(TanganiLaporan::class)->tinjau($l, $this->admin);
    expect($l->fresh()->status)->toBe(StatusLaporan::Ditinjau);
    expect(fn () => app(TanganiLaporan::class)->tinjau($l->fresh(), $this->admin))->toThrow(ValidationException::class);

    app(TanganiLaporan::class)->tandaiDitindaklanjuti($l->fresh(), 'ubah_tujuan', 'Pemilik memperbaiki tujuan.', $this->admin);
    expect($l->fresh()->status)->toBe(StatusLaporan::Ditindaklanjuti)->and($l->fresh()->tindakan)->toBe('ubah_tujuan');

    expect(fn () => app(TanganiLaporan::class)->tolak($l->fresh(), 'Alasan yang cukup.', $this->admin))->toThrow(ValidationException::class);
});

it('mencatat riwayat keputusan di log aktivitas', function () {
    $l = ($this->laporan)();
    app(TanganiLaporan::class)->tolak($l, 'Tidak terbukti menyalahgunakan.', $this->admin);

    $log = Activity::where('log_name', 'moderasi')->where('event', 'ditolak')->first();
    expect($log)->not->toBeNull()->and($log->causer_id)->toBe($this->admin->id)->and($log->subject_id)->toBe($l->id);
});

it('membatasi resource moderasi ke pemegang moderasi.kelola', function () {
    $this->actingAs($this->pengguna)->get('/panel/laporan-penyalahgunaan')->assertForbidden();
    $this->actingAs($this->admin)->get('/panel/laporan-penyalahgunaan')->assertOk();
});

it('menjalankan aksi blokir dan tolak dari tabel panel', function () {
    $a = ($this->laporan)();
    $b = LaporanPenyalahgunaan::create(['kode_dilaporkan' => 'Z', 'kategori' => 'spam', 'ip_hash' => str_repeat('d', 64)]);

    Livewire::actingAs($this->admin)->test(ListLaporanPenyalahgunaan::class)
        ->assertCanSeeTableRecords([$a, $b])
        ->assertActionHidden(TestAction::make('blokir')->table($b))
        ->callAction(TestAction::make('blokir')->table($a), ['alasan' => 'Mengarah ke situs penipuan bank.'])
        ->callAction(TestAction::make('tolak')->table($b), ['alasan' => 'Tidak jelas.']);

    expect($this->tautan->fresh()->status)->toBe(StatusTautan::Diblokir)->and($b->fresh()->status)->toBe(StatusLaporan::Ditolak);
});

it('menampilkan statistik antrean moderasi hanya untuk moderator', function () {
    ($this->laporan)();
    ($this->laporan)(['ip_hash' => str_repeat('b', 64)])->update(['status' => 'ditinjau']);
    $selesai = ($this->laporan)(['ip_hash' => str_repeat('c', 64)]);
    $selesai->forceFill(['created_at' => now()->subHours(4)])->saveQuietly();
    $selesai->update(['status' => 'ditindaklanjuti', 'ditangani_pada' => now()]);

    $this->actingAs($this->admin);
    expect(AntreanModerasiStats::canView())->toBeTrue()
        ->and(AntreanModerasiStats::rataRataJamTindakLanjut())->toBe(4.0);

    $this->actingAs($this->pengguna);
    expect(AntreanModerasiStats::canView())->toBeFalse();

    Livewire::actingAs($this->admin)->test(AntreanModerasiStats::class)->assertSee('Laporan baru')->assertSee('4 jam');
});
