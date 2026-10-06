<?php

use App\Actions\Tautan\BuatTautan;
use App\Actions\Tautan\UbahTautan;
use App\Filament\Resources\TautanPendekResource\Pages\EditTautanPendek;
use App\Models\KunjunganTautan;
use App\Models\TautanPendek;
use App\Models\User;
use App\Support\Tujuan\ResolverDns;
use App\Support\Tujuan\TokenKataSandi;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\SlugTerlarangSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

const UA_PERAMBAN = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

beforeEach(function () {
    Cache::flush();
    $this->pemilik = User::factory()->create();
    $this->t = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Pass001', 'url_tujuan' => 'https://forms.gle/rahasia', 'host_tujuan' => 'forms.gle']);
    $this->t->forceFill(['kata_sandi_hash' => Hash::make('rahasia123')])->saveQuietly();
    $this->kirim = fn (string $kataSandi, ?string $token = null, string $kode = 'Pass001') => $this->withHeaders(['User-Agent' => UA_PERAMBAN])
        ->post('/'.$kode, ['kata_sandi' => $kataSandi, 'token' => $token ?? TokenKataSandi::buat($kode)]);
});

it('menampilkan halaman antara tanpa mengalihkan atau membocorkan tujuan', function () {
    $r = $this->get('/Pass001');

    $r->assertOk()->assertSee('dilindungi kata sandi')->assertDontSee('forms.gle')->assertDontSee('rahasia')->assertSee('name="token"', false);
    expect($r->headers->has('Location'))->toBeFalse()->and($r->headers->has('Set-Cookie'))->toBeFalse();
    expect(KunjunganTautan::count())->toBe(0);
});

it('mengalihkan dan mencatat kunjungan bila kata sandi benar', function () {
    $r = ($this->kirim)('rahasia123');

    $r->assertStatus(302)->assertHeader('Location', 'https://forms.gle/rahasia');
    expect($r->headers->has('Set-Cookie'))->toBeFalse()
        ->and(KunjunganTautan::count())->toBe(1)
        ->and($this->t->fresh()->jumlah_klik)->toBe(1);
});

it('menolak kata sandi salah tanpa mengalihkan dan tanpa mencatat kunjungan', function () {
    ($this->kirim)('salah')->assertOk()->assertSee('Kata sandi salah.')->assertDontSee('forms.gle');
    ($this->kirim)('')->assertOk()->assertSee('Kata sandi salah.');

    expect(KunjunganTautan::count())->toBe(0);
});

it('membatasi 5 percobaan per menit per IP dan kode (429 pada percobaan ke-6)', function () {
    foreach (range(1, 5) as $_) {
        ($this->kirim)('salah')->assertOk();
    }

    ($this->kirim)('salah')->assertStatus(429);
    ($this->kirim)('rahasia123')->assertStatus(429);

    // kode lain atau IP lain tidak terpengaruh
    $lain = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Pass002', 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle']);
    $lain->forceFill(['kata_sandi_hash' => Hash::make('x1234')])->saveQuietly();
    ($this->kirim)('x1234', kode: 'Pass002')->assertStatus(302);
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->post('/Pass001', ['kata_sandi' => 'rahasia123', 'token' => TokenKataSandi::buat('Pass001')])->assertStatus(302);
});

it('menolak token kedaluwarsa, salah kode, atau rusak', function () {
    $lama = TokenKataSandi::buat('Pass001', time() - 3 * TokenKataSandi::JENDELA_DETIK);

    ($this->kirim)('rahasia123', $lama)->assertOk()->assertSee('kedaluwarsa');
    ($this->kirim)('rahasia123', TokenKataSandi::buat('KodeLain'))->assertOk()->assertSee('kedaluwarsa');
    ($this->kirim)('rahasia123', 'bukan-token')->assertOk()->assertSee('kedaluwarsa');
    ($this->kirim)('rahasia123', '')->assertOk()->assertSee('kedaluwarsa');
    expect(KunjunganTautan::count())->toBe(0);
});

it('menerima token pada jendela sebelumnya', function () {
    $token = TokenKataSandi::buat('Pass001', time() - TokenKataSandi::JENDELA_DETIK);

    ($this->kirim)('rahasia123', $token)->assertStatus(302);
});

it('tetap menerapkan status tautan sebelum meminta kata sandi', function () {
    $this->t->forceFill(['status' => 'diblokir'])->saveQuietly();

    $this->get('/Pass001')->assertStatus(410)->assertDontSee('dilindungi kata sandi');
    ($this->kirim)('rahasia123')->assertStatus(410);
});

it('mengonsumsi klik pada tautan berkata sandi yang sekali pakai hanya setelah sandi benar', function () {
    $this->t->forceFill(['sekali_pakai' => true])->saveQuietly();

    ($this->kirim)('salah')->assertOk();
    ($this->kirim)('rahasia123')->assertStatus(302);
    ($this->kirim)('rahasia123')->assertStatus(410);
});

it('mengarahkan POST ke tautan tanpa kata sandi kembali ke alur biasa', function () {
    $biasa = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Biasa01', 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle']);

    $this->post('/Biasa01', ['kata_sandi' => 'x'])->assertRedirect(url('/Biasa01'));
});

it('menyimpan hash saat membuat/mengubah tautan dan menghapusnya', function () {
    $this->seed([PeranDanIzinSeeder::class, SlugTerlarangSeeder::class, AturanDomainSeeder::class, PengaturanSeeder::class]);
    Queue::fake();
    app()->instance(ResolverDns::class, new class extends ResolverDns
    {
        public function resolve(string $host): array
        {
            return ['93.184.216.34'];
        }
    });
    $pengguna = User::factory()->create()->assignRole('pengguna');

    $t = app(BuatTautan::class)->jalankan(['url_tujuan' => 'https://forms.gle/a', 'judul' => 'Sandi', 'kata_sandi' => 'sandi-saya'], $pengguna);
    expect($t->kata_sandi_hash)->not->toBeNull()->and(Hash::check('sandi-saya', $t->kata_sandi_hash))->toBeTrue();

    app(UbahTautan::class)->jalankan($t, ['judul' => 'Judul baru'], $pengguna);
    expect($t->fresh()->kata_sandi_hash)->not->toBeNull();

    app(UbahTautan::class)->jalankan($t, ['kata_sandi' => 'sandi-baru-9'], $pengguna);
    expect(Hash::check('sandi-baru-9', $t->fresh()->kata_sandi_hash))->toBeTrue();

    app(UbahTautan::class)->jalankan($t, ['hapus_kata_sandi' => true], $pengguna);
    expect($t->fresh()->kata_sandi_hash)->toBeNull();

    expect(fn () => app(BuatTautan::class)->jalankan(['url_tujuan' => 'https://forms.gle/a', 'judul' => 'X', 'kata_sandi' => 'abc'], $pengguna))
        ->toThrow(ValidationException::class);
});

it('tidak pernah menampilkan hash di JSON, panel, maupun jejak audit', function () {
    $this->seed([PeranDanIzinSeeder::class]);
    Filament::setCurrentPanel('alias');
    $hash = $this->t->fresh()->makeVisible('kata_sandi_hash')->kata_sandi_hash;

    expect(json_encode($this->t->fresh()))->not->toContain($hash)->and($this->t->fresh()->toArray())->not->toHaveKey('kata_sandi_hash');

    $this->pemilik->assignRole('pengguna');
    $html = Livewire::actingAs($this->pemilik)->test(EditTautanPendek::class, ['record' => $this->t->id])->html();
    expect($html)->not->toContain($hash);

    $this->t->update(['judul' => 'Judul diubah']);
    foreach (Activity::all() as $log) {
        expect(json_encode($log->attribute_changes))->not->toContain($hash)->not->toContain('kata_sandi_hash');
    }
});
