<?php

use App\Actions\Tautan\BuatTautan;
use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Enums\StatusTautan;
use App\Models\SlugTerlarang;
use App\Models\Unit;
use App\Models\User;
use App\Support\Kode\PembangkitKode;
use App\Support\Kode\SinkronPrefiksUnit;
use App\Support\Tujuan\ResolverDns;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, SlugTerlarangSeeder::class, AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    Queue::fake();
    app()->instance(ResolverDns::class, new class extends ResolverDns
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    config(['alias.namespace_unit' => true]);

    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->pmat = Unit::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'jenis' => 'prodi', 'prefiks_slug' => 'pmat']);
    $this->pbio = Unit::create(['kode' => 'PBIO', 'nama' => 'Pendidikan Biologi', 'jenis' => 'prodi', 'prefiks_slug' => 'pbio']);
    $this->anggotaPmat = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->anggotaPbio = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->tanpaUnit = User::factory()->create()->assignRole(Peran::Pengguna->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->anggotaPmat, PeranUnit::Anggota, $this->admin);
    app(TambahAnggotaUnit::class)->jalankan($this->pbio, $this->anggotaPbio, PeranUnit::Anggota, $this->admin);
    $this->data = ['url_tujuan' => 'https://forms.gle/abc', 'judul' => 'Seminar', 'jenis_kepemilikan' => 'pribadi', 'slug_kustom' => 'pmat-seminar'];
});

it('membuat entri slug terlarang awalan otomatis dari prefiks unit', function () {
    $entri = SlugTerlarang::where('pola', 'pmat-')->first();

    expect($entri)->not->toBeNull()
        ->and($entri->cara_cocok->value)->toBe('awalan')
        ->and($entri->keterangan)->toBe('prefiks-unit:'.$this->pmat->id);

    $this->pmat->update(['prefiks_slug' => 'matematika']);
    expect(SlugTerlarang::where('pola', 'pmat-')->exists())->toBeFalse()->and(SlugTerlarang::where('pola', 'matematika-')->exists())->toBeTrue();

    $this->pmat->update(['prefiks_slug' => null]);
    expect(SlugTerlarang::where('keterangan', 'prefiks-unit:'.$this->pmat->id)->exists())->toBeFalse();
});

it('langsung mengaktifkan slug berprefiks bagi anggota unit pemilik prefiks tanpa persetujuan', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->anggotaPmat);

    expect($t->kode)->toBe('pmat-seminar')->and($t->status)->toBe(StatusTautan::Aktif);
});

it('menolak slug berprefiks PMAT bagi anggota PBIO dan pengguna tanpa unit', function () {
    expect(fn () => app(BuatTautan::class)->jalankan($this->data, $this->anggotaPbio))->toThrow(ValidationException::class, 'unit lain');
    expect(fn () => app(BuatTautan::class)->jalankan($this->data, $this->tanpaUnit))->toThrow(ValidationException::class, 'unit lain');
});

it('mengizinkan pembuatan atas nama unit pemilik prefiks', function () {
    $t = app(BuatTautan::class)->jalankan([...$this->data, 'jenis_kepemilikan' => 'unit', 'unit_id' => $this->pmat->id], $this->anggotaPmat);

    expect($t->unit_id)->toBe($this->pmat->id)->and($t->status)->toBe(StatusTautan::Aktif);
});

it('tetap memakai persetujuan untuk slug tanpa prefiks unit', function () {
    $t = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'seminar-umum'], $this->anggotaPmat);

    expect($t->status)->toBe(StatusTautan::MenungguPersetujuan);
});

it('tidak membuat prefiks khusus bila fitur nonaktif', function () {
    config(['alias.namespace_unit' => false]);
    SinkronPrefiksUnit::sinkronSemua();

    expect(SlugTerlarang::where('keterangan', 'like', 'prefiks-unit:%')->count())->toBe(0);

    $t = app(BuatTautan::class)->jalankan($this->data, $this->tanpaUnit);
    expect($t->kode)->toBe('pmat-seminar')->and($t->status)->toBe(StatusTautan::MenungguPersetujuan);
});

it('menyinkronkan semua unit lewat perintah artisan saat fitur diaktifkan', function () {
    config(['alias.namespace_unit' => false]);
    SinkronPrefiksUnit::sinkronSemua();
    config(['alias.namespace_unit' => true]);

    $this->artisan('alias:sinkron-prefiks')->expectsOutputToContain('unit disinkronkan')->assertSuccessful();

    expect(SlugTerlarang::where('pola', 'pmat-')->exists())->toBeTrue()->and(SlugTerlarang::where('pola', 'pbio-')->exists())->toBeTrue();
});

it('tidak memblokir kode acak karena entri prefiks (base62 tanpa strip)', function () {
    expect(app(PembangkitKode::class)->layak('pmat1234'))->toBeTrue();
});
