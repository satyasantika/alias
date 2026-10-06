<?php

use App\Actions\Moderasi\TerimaLaporan;
use App\Actions\Tautan\BlokirTautan;
use App\Actions\Tautan\BuatTautan;
use App\Actions\Tautan\BukaBlokirTautan;
use App\Actions\Tautan\PindahkanKepemilikanTautan;
use App\Actions\Tautan\PindahkanSemuaTautanPengguna;
use App\Actions\Tautan\SetujuiSlugKustom;
use App\Actions\Tautan\TolakSlugKustom;
use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Events\TujuanBermasalah as EventTujuanBermasalah;
use App\Jobs\PeriksaKesehatanTujuan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\LaporanPenyalahgunaanBaru;
use App\Notifications\SlugKustomDiputuskan;
use App\Notifications\SlugKustomMenunggu;
use App\Notifications\TautanDiblokir;
use App\Notifications\TautanDibukaBlokir;
use App\Notifications\TautanDipindahkan;
use App\Notifications\TujuanBermasalah;
use App\Support\Tujuan\ResolverDns;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class, SlugTerlarangSeeder::class, AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    app()->instance(ResolverDns::class, new class extends ResolverDns
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    $this->pmat = Unit::where('kode', 'PMAT')->first();
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin2 = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->adminNonaktif = User::factory()->create(['aktif' => false])->assignRole(Peran::AdminAlias->value);
    $this->pemilik = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->pengelola = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);
    $this->pengelola2 = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pengelola, PeranUnit::Pengelola, $this->admin);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pengelola2, PeranUnit::Pengelola, $this->admin);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pemilik, PeranUnit::Anggota, $this->admin);
    $this->data = ['url_tujuan' => 'https://forms.gle/abc', 'judul' => 'Formulir', 'jenis_kepemilikan' => 'pribadi'];
    Queue::fake([PeriksaKesehatanTujuan::class]);
    Notification::fake();
});

it('memberi tahu admin aktif saat slug menunggu persetujuan, bukan admin nonaktif atau pemilik', function () {
    $t = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'seminar-baru'], $this->pemilik);

    Notification::assertSentTo([$this->admin, $this->admin2], SlugKustomMenunggu::class, fn ($n) => $n->tautan->is($t));
    Notification::assertNotSentTo([$this->adminNonaktif, $this->pemilik], SlugKustomMenunggu::class);
    Notification::assertCount(2);
});

it('tidak memberi notifikasi slug untuk tautan acak yang langsung aktif', function () {
    app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    Notification::assertNothingSent();
});

it('memberi tahu pembuat saat slug disetujui atau ditolak (db + mail)', function () {
    $t = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'seminar-a'], $this->pemilik);
    $t2 = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'seminar-b'], $this->pemilik);
    Notification::fake();

    app(SetujuiSlugKustom::class)->jalankan($t, $this->admin);
    app(TolakSlugKustom::class)->jalankan($t2, 'Slug menyerupai nama resmi unit.', $this->admin);

    Notification::assertSentTo($this->pemilik, SlugKustomDiputuskan::class, fn ($n, $kanal) => $n->disetujui && $n->tautan->is($t) && $kanal === ['database', 'mail']);
    Notification::assertSentTo($this->pemilik, SlugKustomDiputuskan::class, fn ($n) => ! $n->disetujui && $n->alasan === 'Slug menyerupai nama resmi unit.');
    Notification::assertSentToTimes($this->pemilik, SlugKustomDiputuskan::class, 2);
});

it('mengirim notifikasi blokir ke pemilik pribadi dan semua pengelola unit, bukan ke anggota biasa', function () {
    $pribadi = TautanPendek::factory()->milikPribadi($this->pemilik)->create();
    $unit = TautanPendek::factory()->milikUnit($this->pmat, $this->pemilik)->create();

    app(BlokirTautan::class)->jalankan($pribadi, 'Mengarah ke situs penipuan.', $this->admin);
    app(BlokirTautan::class)->jalankan($unit, 'Mengarah ke situs penipuan.', null);

    Notification::assertSentTo($this->pemilik, TautanDiblokir::class, fn ($n) => $n->tautan->is($pribadi) && $n->alasan === 'Mengarah ke situs penipuan.');
    Notification::assertSentTo([$this->pengelola, $this->pengelola2], TautanDiblokir::class, fn ($n) => $n->tautan->is($unit));
    Notification::assertNotSentTo($this->pemilik, TautanDiblokir::class, fn ($n) => $n->tautan->is($unit));
    Notification::assertNotSentTo([$this->admin, $this->adminNonaktif], TautanDiblokir::class);
});

it('mengirim notifikasi buka blokir ke pemilik', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pemilik)->create();
    app(BlokirTautan::class)->jalankan($t, 'Mengarah ke situs penipuan.', $this->admin);
    Notification::fake();

    app(BukaBlokirTautan::class)->jalankan($t, $this->admin, 'Ternyata aman.');

    Notification::assertSentTo($this->pemilik, TautanDibukaBlokir::class);
    Notification::assertNotSentTo($this->pemilik, TautanDiblokir::class);
});

it('tidak mengirim ke pemilik yang nonaktif', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pemilik)->create();
    $this->pemilik->forceFill(['aktif' => false])->save();

    app(BlokirTautan::class)->jalankan($t, 'Mengarah ke situs penipuan.', $this->admin);

    Notification::assertNothingSent();
});

it('memberi tahu pemilik saat tujuan menjadi bermasalah', function () {
    $pribadi = TautanPendek::factory()->milikPribadi($this->pemilik)->create();
    $unit = TautanPendek::factory()->milikUnit($this->pmat, $this->pemilik)->create();

    EventTujuanBermasalah::dispatch($pribadi);
    EventTujuanBermasalah::dispatch($unit);

    Notification::assertSentTo($this->pemilik, TujuanBermasalah::class, fn ($n) => $n->tautan->is($pribadi));
    Notification::assertSentTo([$this->pengelola, $this->pengelola2], TujuanBermasalah::class, fn ($n) => $n->tautan->is($unit));
    Notification::assertNotSentTo($this->pemilik, TujuanBermasalah::class, fn ($n) => $n->tautan->is($unit));
});

it('memberi tahu moderator saat laporan baru masuk, tidak kepada pengguna biasa', function () {
    app(TerimaLaporan::class)->jalankan(['kode' => 'Abc1234', 'kategori' => 'spam'], str_repeat('a', 64));

    Notification::assertSentTo([$this->admin, $this->admin2], LaporanPenyalahgunaanBaru::class);
    Notification::assertNotSentTo([$this->pemilik, $this->adminNonaktif, $this->pengelola], LaporanPenyalahgunaanBaru::class);
});

it('memberi tahu pemilik lama dan baru saat tautan dipindahkan (tautan unit → semua pengelola)', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pemilik)->create();

    app(PindahkanKepemilikanTautan::class)->jalankan($t, $this->pmat, $this->pemilik, 'Pindah ke prodi');

    Notification::assertSentTo([$this->pemilik, $this->pengelola, $this->pengelola2], TautanDipindahkan::class, fn ($n) => $n->jumlah === 1 && $n->tautan->is($t));
    Notification::assertNotSentTo($this->admin, TautanDipindahkan::class);
});

it('mengirim satu ringkasan (bukan per tautan) pada pemindahan massal', function () {
    TautanPendek::factory()->count(5)->milikPribadi($this->pemilik)->create();

    app(PindahkanSemuaTautanPengguna::class)->jalankan($this->pemilik, $this->pmat, $this->admin);

    Notification::assertSentToTimes($this->pengelola, TautanDipindahkan::class, 1);
    Notification::assertSentTo($this->pengelola, TautanDipindahkan::class, fn ($n) => $n->jumlah === 5 && $n->tautan === null);
    Notification::assertSentToTimes($this->pemilik, TautanDipindahkan::class, 1);
});

it('tidak mengirim notifikasi bila transaksi dibatalkan', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pemilik)->create();

    try {
        DB::transaction(function () use ($t) {
            app(BlokirTautan::class)->jalankan($t, 'Mengarah ke situs penipuan.', $this->admin);
            throw new RuntimeException('gagal di tengah transaksi');
        });
    } catch (RuntimeException) {
    }

    expect($t->fresh()->status->value)->toBe('aktif');
    Notification::assertNothingSent();
});

it('menyusun notifikasi database dalam format lonceng Filament dan memakai antrean notifikasi', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Lonc001', 'judul' => 'Judul uji']);
    $n = new TautanDiblokir($t, 'Alasan uji yang cukup.');

    $data = $n->toDatabase($this->pemilik);

    expect($data['format'])->toBe('filament')->and($data['title'])->toBe('Tautan diblokir')->and($data['body'])->toContain('Lonc001')
        ->and($data['status'])->toBe('danger')->and($n->queue)->toBe('notifikasi')
        ->and($n->via($this->pemilik))->toBe(['database', 'mail']);

    $mail = $n->toMail($this->pemilik);
    expect($mail->subject)->toBe('Tautan Alias FKIP diblokir');
});
