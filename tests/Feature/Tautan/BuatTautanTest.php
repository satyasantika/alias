<?php

use App\Actions\Tautan\BuatTautan;
use App\Actions\Tautan\UbahTautan;
use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Enums\StatusTautan;
use App\Filament\Resources\TautanPendekResource\Pages\CreateTautanPendek;
use App\Filament\Resources\TautanPendekResource\Pages\EditTautanPendek;
use App\Filament\Resources\TautanPendekResource\Pages\ListTautanPendek;
use App\Jobs\PeriksaKesehatanTujuan;
use App\Models\Pengaturan;
use App\Models\RiwayatStatusTautan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tujuan\ResolverDns;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Database\Seeders\UnitSeeder;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class, SlugTerlarangSeeder::class, AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    Filament::setCurrentPanel('alias');
    app()->instance(ResolverDns::class, new class extends ResolverDns
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    Queue::fake();

    $this->pmat = Unit::where('kode', 'PMAT')->first();
    $this->pbio = Unit::create(['kode' => 'PBIO', 'nama' => 'Pendidikan Biologi', 'jenis' => 'prodi']);
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->pengelola = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pengelola, PeranUnit::Pengelola, $this->admin);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pengguna, PeranUnit::Anggota, $this->pengelola);

    $this->data = ['url_tujuan' => 'https://docs.google.com/forms/d/e/xxx/viewform', 'judul' => 'Formulir seminar', 'jenis_kepemilikan' => 'pribadi'];
});

it('membuat tautan acak aktif dengan kode 7 karakter, riwayat status, dan antrean cek tujuan', function () {
    $tautan = app(BuatTautan::class)->jalankan($this->data, $this->pengguna);

    expect($tautan->kode)->toMatch('/^[A-Za-z0-9]{7}$/')
        ->and($tautan->status)->toBe(StatusTautan::Aktif)
        ->and($tautan->kode_kustom)->toBeFalse()
        ->and($tautan->pemilik_id)->toBe($this->pengguna->id)
        ->and($tautan->dibuat_oleh)->toBe($this->pengguna->id)
        ->and($tautan->pertama_aktif_pada)->not->toBeNull()
        ->and($tautan->host_tujuan)->toBe('docs.google.com')
        ->and($tautan->url_tujuan_hash)->toBe(hash('sha256', $tautan->url_tujuan));

    $riwayat = RiwayatStatusTautan::where('tautan_pendek_id', $tautan->id)->first();
    expect($riwayat->dari_status)->toBeNull()->and($riwayat->ke_status)->toBe(StatusTautan::Aktif);
    Queue::assertPushed(PeriksaKesehatanTujuan::class, fn ($j) => $j->tautanId === $tautan->id);
});

it('menahan slug kustom pengguna biasa untuk persetujuan (BR-25)', function () {
    $tautan = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'Seminar-PMAT-2026'], $this->pengguna);

    expect($tautan->kode)->toBe('seminar-pmat-2026')
        ->and($tautan->kode_kustom)->toBeTrue()
        ->and($tautan->status)->toBe(StatusTautan::MenungguPersetujuan)
        ->and($tautan->pertama_aktif_pada)->toBeNull();
});

it('langsung mengaktifkan slug kustom pengelola dan admin', function () {
    $a = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'seminar-pengelola'], $this->pengelola);
    $b = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'seminar-admin'], $this->admin);

    expect($a->status)->toBe(StatusTautan::Aktif)->and($b->status)->toBe(StatusTautan::Aktif);
});

it('langsung mengaktifkan slug kustom bila persetujuan dimatikan', function () {
    Pengaturan::where('kunci', 'slug_kustom_perlu_persetujuan')->first()->update(['nilai' => '0']);

    $t = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'bebas-setuju'], $this->pengguna);

    expect($t->status)->toBe(StatusTautan::Aktif);
});

it('menolak slug terlarang, terpakai, dan format salah', function (string $slug) {
    expect(fn () => app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => $slug], $this->pengguna))
        ->toThrow(ValidationException::class);
})->with(['login', 'Spasi Salah', 'ab', 'judol88']);

it('membuat tautan atas nama unit hanya untuk unit tempat ia anggota', function () {
    $ok = app(BuatTautan::class)->jalankan([...$this->data, 'jenis_kepemilikan' => 'unit', 'unit_id' => $this->pmat->id], $this->pengguna);
    expect($ok->unit_id)->toBe($this->pmat->id)->and($ok->pemilik_id)->toBeNull();

    expect(fn () => app(BuatTautan::class)->jalankan([...$this->data, 'jenis_kepemilikan' => 'unit', 'unit_id' => $this->pbio->id], $this->pengguna))
        ->toThrow(ValidationException::class);

    // admin bebas memilih unit mana pun
    expect(app(BuatTautan::class)->jalankan([...$this->data, 'jenis_kepemilikan' => 'unit', 'unit_id' => $this->pbio->id], $this->admin)->unit_id)->toBe($this->pbio->id);
});

it('menegakkan kuota pribadi (BR-23): tautan ke-101 ditolak', function () {
    TautanPendek::factory()->count(100)->milikPribadi($this->pengguna)->create();

    expect(fn () => app(BuatTautan::class)->jalankan($this->data, $this->pengguna))->toThrow(ValidationException::class, 'Kuota');

    $this->pengguna->forceFill(['kuota_tautan' => 101])->save();
    expect(app(BuatTautan::class)->jalankan($this->data, $this->pengguna)->exists)->toBeTrue();
});

it('menghitung tautan terhapus dalam kuota dan membebaskan pemegang tanpa-kuota', function () {
    TautanPendek::factory()->count(100)->milikPribadi($this->pengguna)->create();
    TautanPendek::query()->limit(1)->first()->delete();

    // soft delete tetap menempati kuota? Tidak: kuota menghitung tautan TIDAK terhapus.
    expect(app(BuatTautan::class)->jalankan($this->data, $this->pengguna)->exists)->toBeTrue();

    TautanPendek::factory()->count(100)->milikPribadi($this->admin)->create();
    expect(app(BuatTautan::class)->jalankan($this->data, $this->admin)->exists)->toBeTrue();
});

it('menegakkan kuota unit', function () {
    $this->pmat->update(['kuota_tautan' => 1]);
    app(BuatTautan::class)->jalankan([...$this->data, 'jenis_kepemilikan' => 'unit', 'unit_id' => $this->pmat->id], $this->pengguna);

    expect(fn () => app(BuatTautan::class)->jalankan([...$this->data, 'jenis_kepemilikan' => 'unit', 'unit_id' => $this->pmat->id], $this->pengguna))
        ->toThrow(ValidationException::class, 'Kuota');
});

it('membatasi pembuatan 30 tautan per jam', function () {
    foreach (range(1, 30) as $_) {
        app(BuatTautan::class)->jalankan($this->data, $this->pengguna);
    }

    expect(fn () => app(BuatTautan::class)->jalankan($this->data, $this->pengguna))->toThrow(ValidationException::class, 'Terlalu banyak');
});

it('melarang pengguna tanpa atur-lanjutan memakai redirect 301 atau mematikan pencatatan', function () {
    expect(fn () => app(BuatTautan::class)->jalankan([...$this->data, 'kode_status_redirect' => 301], $this->pengguna))->toThrow(ValidationException::class);
    expect(fn () => app(BuatTautan::class)->jalankan([...$this->data, 'catat_kunjungan' => false], $this->pengguna))->toThrow(ValidationException::class);

    $t = app(BuatTautan::class)->jalankan([...$this->data, 'kode_status_redirect' => 301, 'catat_kunjungan' => false], $this->admin);
    expect($t->kode_status_redirect)->toBe(301)->and($t->catat_kunjungan)->toBeFalse();
});

it('menolak URL tujuan berbahaya, pemantau, dan jadwal terbalik', function () {
    expect(fn () => app(BuatTautan::class)->jalankan([...$this->data, 'url_tujuan' => 'http://127.0.0.1/'], $this->pengguna))->toThrow(ValidationException::class);
    expect(fn () => app(BuatTautan::class)->jalankan($this->data, User::factory()->create()->assignRole(Peran::Pemantau->value)))->toThrow(AuthorizationException::class);
    expect(fn () => app(BuatTautan::class)->jalankan([...$this->data, 'aktif_mulai' => now()->addDay(), 'aktif_sampai' => now()], $this->pengguna))->toThrow(ValidationException::class);
});

it('mengubah URL tujuan tanpa mengubah kode, mencatat lama→baru, dan mengantre cek ulang', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pengguna);
    $kode = $t->kode;
    $t->forceFill(['status_cek_tujuan' => 'sehat'])->saveQuietly();
    Queue::fake();

    app(UbahTautan::class)->jalankan($t, ['url_tujuan' => 'https://drive.google.com/file/d/baru/view', 'judul' => 'Judul baru'], $this->pengguna);

    $t->refresh();
    expect($t->kode)->toBe($kode)
        ->and($t->host_tujuan)->toBe('drive.google.com')
        ->and($t->judul)->toBe('Judul baru')
        ->and($t->status_cek_tujuan->value)->toBe('belum');
    Queue::assertPushed(PeriksaKesehatanTujuan::class);

    $log = Activity::where('subject_type', TautanPendek::class)->where('event', 'updated')->latest()->first();
    expect(json_encode($log->attribute_changes))->toContain('drive.google.com')->toContain('docs.google.com');
});

it('menolak mengubah tautan orang lain dan mengubah opsi lanjutan tanpa hak', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pengguna);

    expect(fn () => app(UbahTautan::class)->jalankan($t, ['judul' => 'X'], User::factory()->create()->assignRole(Peran::Pengguna->value)))->toThrow(AuthorizationException::class);
    expect(fn () => app(UbahTautan::class)->jalankan($t, ['kode_status_redirect' => 301], $this->pengguna))->toThrow(ValidationException::class);
});

it('menghasilkan QR berisi URL pendek (bukan tujuan) dan membatasi akses', function () {
    config(['app.url' => 'https://alias.test', 'alias.domain_pendek' => null]);
    $t = TautanPendek::factory()->milikPribadi($this->pengguna)->create(['kode' => 'Qr12345']);
    $lain = User::factory()->create()->assignRole(Peran::Pengguna->value);

    $svg = $this->actingAs($this->pengguna)->get("/panel/tautan/{$t->id}/qr.svg");
    $svg->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    $png = $this->actingAs($this->pengguna)->get("/panel/tautan/{$t->id}/qr.png");
    $png->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr($png->getContent(), 1, 3))->toBe('PNG');

    // QR dibangkitkan dari URL pendek: bandingkan dengan pembangkitan langsung
    $diharapkan = (new Builder(writer: new SvgWriter, data: 'https://alias.test/Qr12345', errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 400, margin: 12))->build()->getString();
    expect($svg->getContent())->toBe($diharapkan);

    $this->actingAs($lain)->get("/panel/tautan/{$t->id}/qr.svg")->assertForbidden();
    auth()->logout();
    $this->get("/panel/tautan/{$t->id}/qr.svg")->assertRedirect(Filament::getPanel('alias')->getLoginUrl());
    $this->actingAs($this->pengguna)->get("/panel/tautan/{$t->id}/qr.jpg")->assertNotFound();
});

it('tidak menulis berkas apa pun saat membangkitkan QR', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pengguna)->create();
    $sebelum = collect(File::allFiles(storage_path('app')))->count();

    $this->actingAs($this->pengguna)->get("/panel/tautan/{$t->id}/qr.png")->assertOk();

    expect(collect(File::allFiles(storage_path('app')))->count())->toBe($sebelum);
});

it('menampilkan daftar tautan sesuai cakupan dan tab', function () {
    $sendiri = TautanPendek::factory()->milikPribadi($this->pengguna)->create();
    $unit = TautanPendek::factory()->milikUnit($this->pmat, $this->pengelola)->create();
    $lain = TautanPendek::factory()->milikPribadi(User::factory()->create())->create();
    $menunggu = TautanPendek::factory()->milikPribadi($this->pengguna)->denganStatus('menunggu_persetujuan')->create();

    Livewire::actingAs($this->pengguna)->test(ListTautanPendek::class)
        ->assertCanSeeTableRecords([$sendiri, $unit, $menunggu])->assertCanNotSeeTableRecords([$lain])
        ->set('activeTab', 'milik-saya')->assertCanSeeTableRecords([$sendiri, $menunggu])->assertCanNotSeeTableRecords([$unit])
        ->set('activeTab', 'menunggu')->assertCanSeeTableRecords([$menunggu])->assertCanNotSeeTableRecords([$sendiri]);

    Livewire::actingAs($this->admin)->test(ListTautanPendek::class)->assertCanSeeTableRecords([$sendiri, $unit, $lain]);
});

it('membuat tautan lewat formulir panel', function () {
    Livewire::actingAs($this->pengguna)->test(CreateTautanPendek::class)
        ->fillForm(['url_tujuan' => 'https://forms.gle/abc', 'judul' => 'Formulir', 'jenis_kepemilikan' => 'pribadi'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(TautanPendek::where('judul', 'Formulir')->first()->pemilik_id)->toBe($this->pengguna->id);
});

it('menampilkan galat validasi URL pada formulir panel', function () {
    Livewire::actingAs($this->pengguna)->test(CreateTautanPendek::class)
        ->fillForm(['url_tujuan' => 'javascript:alert(1)', 'judul' => 'X', 'jenis_kepemilikan' => 'pribadi'])
        ->call('create')
        ->assertHasFormErrors(['url_tujuan']);
});

it('mengedit tautan lewat formulir panel dan menolak tautan orang lain', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pengguna);

    Livewire::actingAs($this->pengguna)->test(EditTautanPendek::class, ['record' => $t->id])
        ->fillForm(['judul' => 'Diubah'])->call('save')->assertHasNoFormErrors();
    expect($t->fresh()->judul)->toBe('Diubah');

    $this->actingAs(User::factory()->create()->assignRole(Peran::Pengguna->value))->get("/panel/tautan/{$t->id}/edit")->assertNotFound();
});
