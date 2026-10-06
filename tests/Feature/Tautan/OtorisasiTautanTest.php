<?php

use App\Actions\Unit\TambahAnggotaUnit;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Database\QueryException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class]);
    $this->pmat = Unit::where('kode', 'PMAT')->first();
    $this->pbio = Unit::create(['kode' => 'PBIO', 'nama' => 'Pendidikan Biologi', 'jenis' => 'prodi']);
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);

    $this->pengelola = User::factory()->create()->assignRole(Peran::PengelolaUnit->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->pengelola, PeranUnit::Pengelola, $this->admin);
    $this->anggota = User::factory()->create()->assignRole(Peran::Pengguna->value);
    app(TambahAnggotaUnit::class)->jalankan($this->pmat, $this->anggota, PeranUnit::Anggota, $this->pengelola);
    $this->pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->pemantau = User::factory()->create()->assignRole(Peran::Pemantau->value);
    $this->super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);
    $this->lain = User::factory()->create()->assignRole(Peran::Pengguna->value);

    $this->tautan = [
        'sendiri' => fn (User $u) => TautanPendek::factory()->milikPribadi($u)->create(),
        'unitPmat' => fn () => TautanPendek::factory()->milikUnit($this->pmat, $this->anggota)->create(),
        'unitPmatDibuatPengelola' => fn () => TautanPendek::factory()->milikUnit($this->pmat, $this->pengelola)->create(),
        'unitPbio' => fn () => TautanPendek::factory()->milikUnit($this->pbio, $this->lain)->create(),
        'milikLain' => fn () => TautanPendek::factory()->milikPribadi($this->lain)->create(),
    ];
});

// [peran => [aksi => [sendiri, unitPmat(dibuat anggota), unitPmatDibuatPengelola, unitPbio, milikLain]]]
// Kolom 'sendiri' memakai tautan pribadi milik aktor itu sendiri. 1 = diizinkan.
$matriks = [
    'admin' => ['view' => [1, 1, 1, 1, 1], 'update' => [1, 1, 1, 1, 1], 'nonaktifkan' => [1, 1, 1, 1, 1], 'delete' => [1, 1, 1, 1, 1], 'transfer' => [1, 1, 1, 1, 1]],
    'super' => ['view' => [1, 1, 1, 1, 1], 'update' => [1, 1, 1, 1, 1], 'nonaktifkan' => [1, 1, 1, 1, 1], 'delete' => [1, 1, 1, 1, 1], 'transfer' => [1, 1, 1, 1, 1]],
    // pengelola PMAT: unit PMAT penuh; tautan pribadi sendiri; tidak ada akses ke PBIO / milik lain.
    'pengelola' => ['view' => [1, 1, 1, 0, 0], 'update' => [1, 1, 1, 0, 0], 'nonaktifkan' => [1, 1, 1, 0, 0], 'delete' => [1, 1, 1, 0, 0], 'transfer' => [1, 1, 1, 0, 0]],
    // anggota PMAT: lihat tautan unit; ubah/nonaktifkan hanya yang ia buat; tidak hapus/transfer tautan unit.
    'anggota' => ['view' => [1, 1, 1, 0, 0], 'update' => [1, 1, 0, 0, 0], 'nonaktifkan' => [1, 1, 0, 0, 0], 'delete' => [1, 0, 0, 0, 0], 'transfer' => [1, 0, 0, 0, 0]],
    'pengguna' => ['view' => [1, 0, 0, 0, 0], 'update' => [1, 0, 0, 0, 0], 'nonaktifkan' => [1, 0, 0, 0, 0], 'delete' => [1, 0, 0, 0, 0], 'transfer' => [1, 0, 0, 0, 0]],
    'pemantau' => ['view' => [0, 0, 0, 0, 0], 'update' => [0, 0, 0, 0, 0], 'nonaktifkan' => [0, 0, 0, 0, 0], 'delete' => [0, 0, 0, 0, 0], 'transfer' => [0, 0, 0, 0, 0]],
];
$kolom = ['sendiri', 'unitPmat', 'unitPmatDibuatPengelola', 'unitPbio', 'milikLain'];

foreach ($matriks as $peran => $aksiAksi) {
    foreach ($aksiAksi as $aksi => $baris) {
        foreach ($kolom as $i => $nama) {
            $diharapkan = (bool) $baris[$i];
            it("{$peran} ".($diharapkan ? 'boleh' : 'tidak boleh')." {$aksi} tautan {$nama}", function () use ($peran, $aksi, $nama, $diharapkan) {
                $aktor = $this->{$peran === 'admin' ? 'admin' : $peran};
                $tautan = $nama === 'sendiri' ? ($this->tautan['sendiri'])($aktor) : ($this->tautan[$nama])();

                expect($aktor->can($aksi, $tautan))->toBe($diharapkan);
            });
        }
    }
}

it('mencakup scope terlihatOleh sesuai BR-20', function () {
    $sendiri = ($this->tautan['sendiri'])($this->anggota);
    $unitPmat = ($this->tautan['unitPmat'])();
    $unitPbio = ($this->tautan['unitPbio'])();
    $milikLain = ($this->tautan['milikLain'])();

    $lihat = fn (User $u) => TautanPendek::query()->terlihatOleh($u)->pluck('id')->all();

    expect($lihat($this->anggota))->toEqualCanonicalizing([$sendiri->id, $unitPmat->id])
        ->and($lihat($this->pengelola))->toEqualCanonicalizing([$unitPmat->id])
        ->and($lihat($this->admin))->toHaveCount(4)
        ->and($lihat($this->pemantau))->toBe([]);
});

it('menolak data kepemilikan tidak konsisten lewat CHECK/trigger', function (array $atribut) {
    $tautan = TautanPendek::factory()->milikPribadi($this->pengguna)->make($atribut);

    expect(fn () => $tautan->save())->toThrow(QueryException::class);
})->with([
    'pribadi tanpa pemilik' => [['pemilik_id' => null]],
    'pribadi dengan unit' => [fn () => ['unit_id' => Unit::first()->id]],
    'kode redirect 307' => [['kode_status_redirect' => 307]],
    'jadwal terbalik' => [['aktif_mulai' => now()->addDay(), 'aktif_sampai' => now()]],
]);

it('menolak unit tanpa unit_id dan unit dengan pemilik', function () {
    expect(fn () => TautanPendek::factory()->milikPribadi($this->pengguna)->create(['jenis_kepemilikan' => 'unit']))->toThrow(QueryException::class);
});

it('membedakan kode huruf besar dan kecil (ascii_bin)', function () {
    TautanPendek::factory()->milikPribadi($this->pengguna)->create(['kode' => 'Abc1234']);
    TautanPendek::factory()->milikPribadi($this->pengguna)->create(['kode' => 'abc1234']);

    expect(TautanPendek::where('kode', 'Abc1234')->count())->toBe(1)
        ->and(TautanPendek::where('kode', 'abc1234')->count())->toBe(1);

    expect(fn () => TautanPendek::factory()->milikPribadi($this->pengguna)->create(['kode' => 'Abc1234']))->toThrow(QueryException::class);
});

it('menghitung status efektif (BR-10)', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pengguna)->create();
    expect($t->statusEfektif()->value)->toBe('dapat_dialihkan');

    $t->aktif_mulai = now()->addHour();
    expect($t->statusEfektif()->value)->toBe('terjadwal');

    $t->aktif_mulai = null;
    $t->aktif_sampai = now()->subMinute();
    expect($t->statusEfektif()->value)->toBe('kedaluwarsa');

    $t->aktif_sampai = null;
    $t->batas_klik = 5;
    $t->jumlah_klik = 5;
    expect($t->statusEfektif()->value)->toBe('habis');

    $t->batas_klik = null;
    $t->sekali_pakai = true;
    $t->dipakai_pada = now();
    expect($t->statusEfektif()->value)->toBe('habis');

    $t->sekali_pakai = false;
    $t->forceFill(['status' => 'diblokir']);
    expect($t->statusEfektif()->value)->toBe('tidak_tersedia');
});

it('membentuk URL pendek dari domain pendek atau APP_URL', function () {
    config(['app.url' => 'https://alias.test', 'alias.domain_pendek' => null]);
    $t = TautanPendek::factory()->milikPribadi($this->pengguna)->make(['kode' => 'Xyz9876']);
    expect($t->url_pendek)->toBe('https://alias.test/Xyz9876');

    config(['alias.domain_pendek' => 'go.fkip.unsil.ac.id']);
    expect($t->url_pendek)->toBe('https://go.fkip.unsil.ac.id/Xyz9876');
});

it('menyembunyikan kata sandi tautan dan mengunci status dari pengisian massal', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pengguna)->create();
    $t->forceFill(['kata_sandi_hash' => 'rahasia'])->save();

    expect($t->toArray())->not->toHaveKey('kata_sandi_hash');
    expect(fn () => $t->fill(['status' => 'diblokir']))->toThrow(MassAssignmentException::class);
});

it('mencatat perubahan tujuan di jejak audit tanpa penghitung klik', function () {
    $t = TautanPendek::factory()->milikPribadi($this->pengguna)->create();
    $t->update(['url_tujuan' => 'https://baru.unsil.ac.id/x']);
    $t->forceFill(['jumlah_klik' => 9])->save();

    $log = Activity::query()->where('subject_type', TautanPendek::class)->where('event', 'updated')->get();
    expect($log)->toHaveCount(1)->and(json_encode($log[0]->attribute_changes))->toContain('baru.unsil.ac.id')->not->toContain('jumlah_klik');
});
