<?php

use App\Enums\Peran;
use App\Filament\Resources\LaporanPenyalahgunaanResource\Pages\ListLaporanPenyalahgunaan;
use App\Filament\Resources\TautanPendekResource\Pages\ListTautanPendek;
use App\Filament\Resources\TautanPendekResource\Pages\ViewTautanPendek;
use App\Models\LaporanPenyalahgunaan;
use App\Models\TautanPendek;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

const PAYLOAD = '<script>alert("xss")</script>';

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class]);
    Filament::setCurrentPanel('alias');
    $this->pemilik = User::factory()->create()->assignRole(Peran::Pengguna->value);
    $this->tautan = TautanPendek::factory()->milikPribadi($this->pemilik)->create([
        'kode' => 'Xss0001', 'judul' => PAYLOAD, 'keterangan' => PAYLOAD, 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle',
    ]);
});

it('meng-escape judul berbahaya pada pratinjau, galat, dan halaman kata sandi', function () {
    $this->get('/Xss0001+')->assertOk()->assertDontSee(PAYLOAD, false)->assertSee('&lt;script&gt;', false);

    $this->tautan->forceFill(['kata_sandi_hash' => 'x'])->saveQuietly();
    $this->get('/Xss0001')->assertOk()->assertDontSee(PAYLOAD, false);

    $this->tautan->forceFill(['kata_sandi_hash' => null, 'status' => 'diblokir'])->saveQuietly();
    $this->get('/Xss0001')->assertStatus(410)->assertDontSee(PAYLOAD, false);
});

it('meng-escape judul berbahaya di daftar dan halaman statistik panel', function () {
    $html = Livewire::actingAs($this->pemilik)->test(ListTautanPendek::class)->html();
    expect($html)->not->toContain(PAYLOAD);

    $html = Livewire::actingAs($this->pemilik)->test(ViewTautanPendek::class, ['record' => $this->tautan->id])->html();
    expect($html)->not->toContain(PAYLOAD);
});

it('meng-escape keterangan laporan berbahaya di panel moderasi', function () {
    $admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
    LaporanPenyalahgunaan::create([
        'tautan_pendek_id' => $this->tautan->id, 'kode_dilaporkan' => '<img src=x onerror=alert(1)>', 'kategori' => 'spam',
        'keterangan' => PAYLOAD, 'ip_hash' => str_repeat('a', 64),
    ]);

    $html = Livewire::actingAs($admin)->test(ListLaporanPenyalahgunaan::class)->html();

    expect($html)->not->toContain(PAYLOAD)->not->toContain('<img src=x onerror');
});

it('meng-escape masukan berbahaya pada formulir publik saat galat validasi', function () {
    $r = $this->post('/lapor', ['kode' => PAYLOAD, 'kategori' => 'tidak-ada']);

    $r->assertSessionHasErrors();
    $html = $this->followingRedirects()->get('/lapor')->getContent();
    expect($html)->not->toContain(PAYLOAD);
});

it('tidak memakai {!! !!} pada seluruh tampilan publik', function () {
    $berkas = array_merge(glob(resource_path('views/*.blade.php')), glob(resource_path('views/*/*.blade.php')));

    expect($berkas)->not->toBeEmpty();
    foreach ($berkas as $b) {
        expect(file_get_contents($b))->not->toContain('{!!', basename($b));
    }
});
