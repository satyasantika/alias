<?php

use App\Actions\Tautan\AjukanUlangSlug;
use App\Actions\Tautan\AktifkanTautan;
use App\Actions\Tautan\BlokirTautan;
use App\Actions\Tautan\BuatTautan;
use App\Actions\Tautan\BukaBlokirTautan;
use App\Actions\Tautan\HapusTautan;
use App\Actions\Tautan\NonaktifkanTautan;
use App\Actions\Tautan\SetujuiSlugKustom;
use App\Actions\Tautan\TolakSlugKustom;
use App\Actions\Tautan\UbahStatusTautan;
use App\Enums\Peran;
use App\Enums\StatusTautan;
use App\Filament\Resources\TautanPendekResource\Pages\AntreanPersetujuan;
use App\Filament\Resources\TautanPendekResource\Pages\EditTautanPendek;
use App\Filament\Resources\TautanPendekResource\RelationManagers\RiwayatStatusRelationManager;
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
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class, SlugTerlarangSeeder::class, AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    Queue::fake();
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
    $this->pemilik = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->lain = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->pmat = Unit::where('kode', 'PMAT')->first();
    $this->data = ['url_tujuan' => 'https://forms.gle/abc', 'judul' => 'Formulir', 'jenis_kepemilikan' => 'pribadi'];
});

function slugMenunggu($pemilik): TautanPendek
{
    return app(BuatTautan::class)->jalankan([...test()->data, 'slug_kustom' => 'seminar-baru'], $pemilik);
}

it('mendefinisikan transisi sah sesuai diagram §8.1', function (string $dari, string $ke, bool $sah) {
    expect(UbahStatusTautan::sah(StatusTautan::from($dari), StatusTautan::from($ke)))->toBe($sah);
})->with([
    ['menunggu_persetujuan', 'aktif', true], ['menunggu_persetujuan', 'ditolak', true], ['menunggu_persetujuan', 'diblokir', false],
    ['menunggu_persetujuan', 'dinonaktifkan', false], ['ditolak', 'menunggu_persetujuan', true], ['ditolak', 'aktif', false],
    ['aktif', 'dinonaktifkan', true], ['aktif', 'diblokir', true], ['aktif', 'ditolak', false], ['aktif', 'menunggu_persetujuan', false],
    ['dinonaktifkan', 'aktif', true], ['dinonaktifkan', 'diblokir', true], ['dinonaktifkan', 'ditolak', false],
    ['diblokir', 'aktif', true], ['diblokir', 'dinonaktifkan', false], ['diblokir', 'menunggu_persetujuan', false],
]);

it('menyetujui slug: aktif, pertama_aktif_pada terisi, riwayat tercatat', function () {
    $t = slugMenunggu($this->pemilik);
    expect($t->status)->toBe(StatusTautan::MenungguPersetujuan);

    app(SetujuiSlugKustom::class)->jalankan($t, $this->admin);

    $t->refresh();
    expect($t->status)->toBe(StatusTautan::Aktif)
        ->and($t->pertama_aktif_pada)->not->toBeNull()
        ->and($t->disetujui_oleh)->toBe($this->admin->id);
    $r = RiwayatStatusTautan::where('tautan_pendek_id', $t->id)->orderByDesc('id')->first();
    expect($r->dari_status)->toBe(StatusTautan::MenungguPersetujuan)->and($r->ke_status)->toBe(StatusTautan::Aktif)->and($r->oleh)->toBe($this->admin->id);
});

it('menolak slug dengan alasan wajib ≥ 10 karakter', function () {
    $t = slugMenunggu($this->pemilik);

    expect(fn () => app(TolakSlugKustom::class)->jalankan($t, 'pendek', $this->admin))->toThrow(ValidationException::class);

    app(TolakSlugKustom::class)->jalankan($t, 'Slug menyerupai nama resmi unit.', $this->admin);
    expect($t->fresh()->status)->toBe(StatusTautan::Ditolak)->and($t->fresh()->alasan_status)->toBe('Slug menyerupai nama resmi unit.');
});

it('hanya admin yang boleh menyetujui/menolak slug', function () {
    $t = slugMenunggu($this->pemilik);

    expect(fn () => app(SetujuiSlugKustom::class)->jalankan($t, $this->pemilik))->toThrow(AuthorizationException::class);
    expect(fn () => app(TolakSlugKustom::class)->jalankan($t, 'Alasan yang cukup panjang', $this->pemilik))->toThrow(AuthorizationException::class);
});

it('mengizinkan sistem menolak slug kedaluwarsa tanpa alasan minimal (BR-25)', function () {
    $t = slugMenunggu($this->pemilik);

    app(TolakSlugKustom::class)->jalankan($t, 'Kedaluwarsa tanpa keputusan', null);

    $r = RiwayatStatusTautan::where('tautan_pendek_id', $t->id)->orderByDesc('id')->first();
    expect($t->fresh()->status)->toBe(StatusTautan::Ditolak)->and($r->oleh)->toBeNull();
});

it('mengajukan ulang slug yang ditolak dengan slug baru', function () {
    $t = slugMenunggu($this->pemilik);
    app(TolakSlugKustom::class)->jalankan($t, 'Slug menyerupai nama resmi unit.', $this->admin);

    expect(fn () => app(AjukanUlangSlug::class)->jalankan($t, 'login', $this->pemilik))->toThrow(ValidationException::class);
    expect(fn () => app(AjukanUlangSlug::class)->jalankan($t, 'slug-lain', $this->lain))->toThrow(AuthorizationException::class);

    app(AjukanUlangSlug::class)->jalankan($t, 'Seminar-Revisi', $this->pemilik);

    expect($t->fresh()->kode)->toBe('seminar-revisi')->and($t->fresh()->status)->toBe(StatusTautan::MenungguPersetujuan);
});

it('menonaktifkan dan mengaktifkan kembali oleh pemilik tanpa alasan', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    app(NonaktifkanTautan::class)->jalankan($t, $this->pemilik);
    expect($t->fresh()->status)->toBe(StatusTautan::Dinonaktifkan);

    app(AktifkanTautan::class)->jalankan($t, $this->pemilik);
    expect($t->fresh()->status)->toBe(StatusTautan::Aktif);
});

it('mewajibkan alasan saat admin menonaktifkan tautan orang lain', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    expect(fn () => app(NonaktifkanTautan::class)->jalankan($t, $this->admin))->toThrow(ValidationException::class);
    expect(fn () => app(NonaktifkanTautan::class)->jalankan($t, $this->lain))->toThrow(AuthorizationException::class);

    app(NonaktifkanTautan::class)->jalankan($t, $this->admin, 'Permintaan pemilik via surel.');
    expect($t->fresh()->status)->toBe(StatusTautan::Dinonaktifkan);
});

it('memblokir dengan alasan wajib dan menolak pemilik membuka blokir (BR-26)', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    expect(fn () => app(BlokirTautan::class)->jalankan($t, '', $this->admin))->toThrow(ValidationException::class);
    expect(fn () => app(BlokirTautan::class)->jalankan($t, 'Alasan yang cukup panjang', $this->pemilik))->toThrow(AuthorizationException::class);

    app(BlokirTautan::class)->jalankan($t, 'Tujuan mengarah ke situs penipuan.', $this->admin);
    expect($t->fresh()->status)->toBe(StatusTautan::Diblokir);

    // pemilik tidak dapat mengaktifkan, mengubah, atau membuka blokir
    expect(fn () => app(AktifkanTautan::class)->jalankan($t, $this->pemilik))->toThrow(AuthorizationException::class);
    expect(fn () => app(NonaktifkanTautan::class)->jalankan($t, $this->pemilik))->toThrow(AuthorizationException::class);
    expect(fn () => app(BukaBlokirTautan::class)->jalankan($t, $this->pemilik))->toThrow(AuthorizationException::class);
    expect($this->pemilik->can('update', $t))->toBeFalse();

    app(BukaBlokirTautan::class)->jalankan($t, $this->admin, 'Ternyata aman.');
    expect($t->fresh()->status)->toBe(StatusTautan::Aktif);
});

it('mengizinkan sistem memblokir otomatis (oleh null) dan mencatat riwayat', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    app(BlokirTautan::class)->jalankan($t, 'Otomatis: menunggu tinjauan moderator', null);

    $r = RiwayatStatusTautan::where('tautan_pendek_id', $t->id)->orderByDesc('id')->first();
    expect($t->fresh()->status)->toBe(StatusTautan::Diblokir)->and($r->oleh)->toBeNull();
});

it('menghapus tautan yang pernah aktif secara soft delete dan mengunci slug', function () {
    $t = app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'slug-terkunci'], $this->admin);

    app(HapusTautan::class)->jalankan($t, $this->admin);

    expect(TautanPendek::find($t->id))->toBeNull()
        ->and(TautanPendek::withTrashed()->find($t->id)->dihapus_oleh)->toBe($this->admin->id);

    // slug yang sama tidak dapat dipakai lagi (BR-04)
    expect(fn () => app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'slug-terkunci'], $this->pemilik))->toThrow(ValidationException::class, 'sudah dipakai');
});

it('menghapus permanen tautan yang belum pernah aktif sehingga slug bebas', function () {
    $t = slugMenunggu($this->pemilik);
    app(TolakSlugKustom::class)->jalankan($t, 'Slug menyerupai nama resmi unit.', $this->admin);

    app(HapusTautan::class)->jalankan($t, $this->pemilik);

    expect(TautanPendek::withTrashed()->find($t->id))->toBeNull();
    expect(app(BuatTautan::class)->jalankan([...$this->data, 'slug_kustom' => 'seminar-baru'], $this->lain)->kode)->toBe('seminar-baru');
});

it('menolak orang lain dan pemilik menghapus tautan diblokir', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    expect(fn () => app(HapusTautan::class)->jalankan($t, $this->lain))->toThrow(AuthorizationException::class);

    app(BlokirTautan::class)->jalankan($t, 'Tujuan mengarah ke situs penipuan.', $this->admin);
    expect(fn () => app(HapusTautan::class)->jalankan($t, $this->pemilik))->toThrow(ValidationException::class);
});

it('menolak transisi tidak sah lewat Action', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    expect(fn () => app(SetujuiSlugKustom::class)->jalankan($t, $this->admin))->toThrow(ValidationException::class)
        ->and(fn () => app(AktifkanTautan::class)->jalankan($t, $this->pemilik))->toThrow(ValidationException::class);
});

it('memperlihatkan antrean persetujuan hanya kepada admin dan menyetujui dari antrean', function () {
    $menunggu = slugMenunggu($this->pemilik);
    $aktif = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    $this->actingAs($this->pemilik)->get('/panel/tautan/persetujuan')->assertForbidden();
    $this->actingAs($this->admin)->get('/panel/tautan/persetujuan')->assertOk();

    Livewire::actingAs($this->admin)->test(AntreanPersetujuan::class)
        ->assertCanSeeTableRecords([$menunggu])->assertCanNotSeeTableRecords([$aktif])
        ->callAction(TestAction::make('setujui')->table($menunggu));

    expect($menunggu->fresh()->status)->toBe(StatusTautan::Aktif);
});

it('menampilkan aksi status sesuai hak pada halaman edit', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);

    Livewire::actingAs($this->pemilik)->test(EditTautanPendek::class, ['record' => $t->id])
        ->assertActionVisible('nonaktifkan')->assertActionHidden('blokir')->assertActionHidden('setujui')
        ->callAction('nonaktifkan');
    expect($t->fresh()->status)->toBe(StatusTautan::Dinonaktifkan);

    Livewire::actingAs($this->admin)->test(EditTautanPendek::class, ['record' => $t->id])
        ->assertActionVisible('blokir')->assertActionVisible('aktifkan');
});

it('mencatat riwayat status lewat relation manager read-only', function () {
    $t = app(BuatTautan::class)->jalankan($this->data, $this->pemilik);
    app(NonaktifkanTautan::class)->jalankan($t, $this->pemilik);

    Livewire::actingAs($this->pemilik)->test(
        RiwayatStatusRelationManager::class,
        ['ownerRecord' => $t, 'pageClass' => EditTautanPendek::class],
    )->assertCanSeeTableRecords($t->riwayatStatus);
});
