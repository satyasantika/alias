<?php

use App\Actions\Tautan\BuatTautan;
use App\Enums\Peran;
use App\Filament\Pages\PengaturanSistem;
use App\Jobs\PeriksaKesehatanTujuan;
use App\Models\Pengaturan as ModelPengaturan;
use App\Models\User;
use App\Support\Pengaturan;
use App\Support\Tujuan\ResolverDns;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, SlugTerlarangSeeder::class, AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    Queue::fake([PeriksaKesehatanTujuan::class]);
    Filament::setCurrentPanel('alias');
    app()->instance(ResolverDns::class, new class extends ResolverDns
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    $this->super = User::factory()->create()->assignRole(Peran::SuperAdmin->value);
    $this->super->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    $this->pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);
});

function nilaiForm(array $beda = []): array
{
    return [
        'kuota_bawaan_pengguna' => 100, 'kuota_bawaan_unit' => 500, 'slug_kustom_perlu_persetujuan' => true, 'mode_domain' => 'bebas',
        'retensi_kunjungan_bulan' => 12, 'retensi_kunjungan_bot_hari' => 30, 'retensi_log_login_hari' => 90,
        'ambang_blokir_otomatis' => 3, 'hari_kedaluwarsa_persetujuan' => 14, 'hari_pengingat_kedaluwarsa' => 7,
        'teks_pemberitahuan_privasi' => 'Ringkasan privasi.', ...$beda,
    ];
}

it('hanya super-admin yang dapat membuka pengaturan sistem; admin-alias 403', function () {
    $this->actingAs($this->admin)->get('/panel/pengaturan-sistem')->assertForbidden();
    $this->actingAs($this->pengguna)->get('/panel/pengaturan-sistem')->assertForbidden();
    $this->actingAs($this->super)->get('/panel/pengaturan-sistem')->assertOk()->assertSee('Pengaturan sistem');
});

it('mengisi formulir dari nilai tersimpan', function () {
    Livewire::actingAs($this->super)->test(PengaturanSistem::class)
        ->assertFormSet(['kuota_bawaan_pengguna' => 100, 'mode_domain' => 'bebas', 'slug_kustom_perlu_persetujuan' => true, 'ambang_blokir_otomatis' => 3]);
});

it('mengubah kuota bawaan ke 2 sehingga tautan ke-3 ditolak tanpa deploy', function () {
    $data = ['url_tujuan' => 'https://forms.gle/abc', 'judul' => 'Uji'];
    expect(Pengaturan::ambil('kuota_bawaan_pengguna'))->toBe(100);

    Livewire::actingAs($this->super)->test(PengaturanSistem::class)
        ->fillForm(nilaiForm(['kuota_bawaan_pengguna' => 2]))->call('simpan')->assertHasNoFormErrors();

    expect(Pengaturan::ambil('kuota_bawaan_pengguna'))->toBe(2);
    app(BuatTautan::class)->jalankan($data, $this->pengguna);
    app(BuatTautan::class)->jalankan($data, $this->pengguna);
    expect(fn () => app(BuatTautan::class)->jalankan($data, $this->pengguna))->toThrow(ValidationException::class, 'Kuota');
});

it('menyimpan tiap tipe nilai dengan benar dan membersihkan cache', function () {
    expect(Pengaturan::ambil('mode_domain'))->toBe('bebas');   // memanaskan cache

    Livewire::actingAs($this->super)->test(PengaturanSistem::class)
        ->fillForm(nilaiForm(['mode_domain' => 'daftar_putih', 'slug_kustom_perlu_persetujuan' => false, 'ambang_blokir_otomatis' => 0, 'teks_pemberitahuan_privasi' => "Baris satu\nBaris dua"]))
        ->call('simpan')->assertHasNoFormErrors()->assertNotified();

    expect(Pengaturan::ambil('mode_domain'))->toBe('daftar_putih')
        ->and(Pengaturan::ambil('slug_kustom_perlu_persetujuan'))->toBeFalse()
        ->and(Pengaturan::ambil('ambang_blokir_otomatis'))->toBe(0)
        ->and(Pengaturan::ambil('teks_pemberitahuan_privasi'))->toBe("Baris satu\nBaris dua")
        ->and(ModelPengaturan::where('kunci', 'mode_domain')->value('diubah_oleh'))->toBe($this->super->id);
});

it('memvalidasi rentang nilai', function (string $kunci, mixed $nilai) {
    Livewire::actingAs($this->super)->test(PengaturanSistem::class)
        ->fillForm(nilaiForm([$kunci => $nilai]))->call('simpan')->assertHasFormErrors([$kunci]);
})->with([
    ['kuota_bawaan_pengguna', 0],
    ['kuota_bawaan_unit', -5],
    ['retensi_kunjungan_bulan', 0],
    ['retensi_kunjungan_bulan', 61],
    ['retensi_log_login_hari', 3],
    ['ambang_blokir_otomatis', 99],
    ['hari_kedaluwarsa_persetujuan', 0],
    ['mode_domain', 'sembarang'],
    ['teks_pemberitahuan_privasi', ''],
]);

it('menolak simpan oleh pengguna tanpa hak walau memanggil langsung', function () {
    Livewire::actingAs($this->admin)->test(PengaturanSistem::class)->assertForbidden();
});

it('mencatat perubahan di log aktivitas tanpa menyimpan kata sandi apa pun', function () {
    Livewire::actingAs($this->super)->test(PengaturanSistem::class)->fillForm(nilaiForm(['kuota_bawaan_unit' => 750]))->call('simpan');

    $log = Activity::where('log_name', 'pengaturan')->where('event', 'updated')->get();
    expect($log)->not->toBeEmpty()->and($log->contains(fn ($l) => str_contains(json_encode($l->attribute_changes), '750')))->toBeTrue();
});
