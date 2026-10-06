<?php

use App\Actions\Pengalihan\KonsumsiKlik;
use App\Models\KunjunganTautan;
use App\Models\TautanPendek;
use App\Models\User;
use App\Support\Tujuan\TeruskanQuery;

const UA_BROWSER = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

beforeEach(function () {
    $this->pemilik = User::factory()->create();
    $this->buat = fn (array $atribut) => TautanPendek::factory()->milikPribadi($this->pemilik)
        ->create(['url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle', ...$atribut]);
    $this->klik = fn (string $kode, string $ua = UA_BROWSER) => $this->withHeaders(['User-Agent' => $ua])->get('/'.$kode);
});

it('mengalihkan tepat satu kali untuk tautan sekali pakai walau diklik 20 kali', function () {
    $t = ($this->buat)(['kode' => 'Sekali1', 'sekali_pakai' => true]);

    $status = collect(range(1, 20))->map(fn () => ($this->klik)('Sekali1')->getStatusCode());

    expect($status->filter(fn ($s) => $s === 302))->toHaveCount(1)
        ->and($status->filter(fn ($s) => $s === 410))->toHaveCount(19)
        ->and($t->fresh()->dipakai_pada)->not->toBeNull()
        ->and($t->fresh()->jumlah_klik)->toBe(1);
});

it('menjamin KonsumsiKlik atomik: hanya satu UPDATE yang berhasil', function () {
    $t = ($this->buat)(['kode' => 'Atomik1', 'sekali_pakai' => true]);

    $hasil = collect(range(1, 20))->map(fn () => app(KonsumsiKlik::class)->jalankan($t));

    expect($hasil->filter()->count())->toBe(1);
});

it('menolak klik ke-6 pada batas_klik 5 dengan 410', function () {
    ($this->buat)(['kode' => 'Batas01', 'batas_klik' => 5]);

    foreach (range(1, 5) as $_) {
        ($this->klik)('Batas01')->assertStatus(302);
    }

    ($this->klik)('Batas01')->assertStatus(410)->assertSee('Kuota klik');
    expect(TautanPendek::where('kode', 'Batas01')->value('jumlah_klik'))->toBe(5);
});

it('mencatat kunjungan satu kali per klik berbatas tanpa menghitung dua kali', function () {
    ($this->buat)(['kode' => 'Batas02', 'batas_klik' => 10]);

    foreach (range(1, 3) as $_) {
        ($this->klik)('Batas02')->assertStatus(302);
    }

    expect(TautanPendek::where('kode', 'Batas02')->value('jumlah_klik'))->toBe(3)
        ->and(KunjunganTautan::count())->toBe(3);
});

it('tidak menghabiskan tautan sekali pakai oleh pratinjau bot WhatsApp (BR-14)', function () {
    $t = ($this->buat)(['kode' => 'BotWa01', 'sekali_pakai' => true]);

    foreach (['WhatsApp/2.23.20.0 A', 'TelegramBot (like TwitterBot)', 'facebookexternalhit/1.1', 'Slackbot-LinkExpanding 1.0'] as $bot) {
        ($this->klik)('BotWa01', $bot)->assertStatus(200)->assertSee('Buka tautan ini di peramban')->assertDontSee('forms.gle');
    }

    expect($t->fresh()->dipakai_pada)->toBeNull()->and($t->fresh()->jumlah_klik)->toBe(0);
    expect(KunjunganTautan::where('bot', true)->count())->toBe(4);

    ($this->klik)('BotWa01')->assertStatus(302);
    ($this->klik)('BotWa01')->assertStatus(410);
});

it('tidak menghabiskan tautan berbatas klik oleh bot dan oleh HEAD', function () {
    $t = ($this->buat)(['kode' => 'BotBts1', 'batas_klik' => 1]);

    ($this->klik)('BotBts1', 'curl/8.4.0')->assertStatus(200);
    $this->withHeaders(['User-Agent' => UA_BROWSER])->call('HEAD', '/BotBts1')->assertStatus(302);

    expect($t->fresh()->jumlah_klik)->toBe(0);
    ($this->klik)('BotBts1')->assertStatus(302);
});

it('tetap mengalihkan bot pada tautan biasa dan mencatatnya sebagai bot', function () {
    ($this->buat)(['kode' => 'BotBias']);

    ($this->klik)('BotBias', 'WhatsApp/2.23.20.0 A')->assertStatus(302);

    expect(KunjunganTautan::firstOrFail()->bot)->toBeTrue()->and(TautanPendek::where('kode', 'BotBias')->value('jumlah_klik'))->toBe(0);
});

it('menerapkan jadwal: belum aktif 404 dan kedaluwarsa 410', function () {
    ($this->buat)(['kode' => 'Jdw0001', 'aktif_mulai' => now()->addHour()]);
    ($this->buat)(['kode' => 'Jdw0002', 'aktif_sampai' => now()->addHour()]);

    ($this->klik)('Jdw0001')->assertStatus(404)->assertSee('belum aktif');
    ($this->klik)('Jdw0002')->assertStatus(302);

    $this->travel(2)->hours();
    ($this->klik)('Jdw0002')->assertStatus(410)->assertSee('kedaluwarsa');
    ($this->klik)('Jdw0001')->assertStatus(302);
});

it('meneruskan query permintaan tanpa menimpa parameter tujuan dan mempertahankan fragmen (BR-15)', function () {
    ($this->buat)(['kode' => 'Query01', 'teruskan_query' => true, 'url_tujuan' => 'https://forms.gle/x?a=1#bagian']);

    $r = $this->get('/Query01?utm_source=wa&a=2');

    expect($r->headers->get('Location'))->toBe('https://forms.gle/x?a=1&utm_source=wa#bagian');
});

it('tidak meneruskan query bila opsi nonaktif', function () {
    ($this->buat)(['kode' => 'Query02', 'url_tujuan' => 'https://forms.gle/x?a=1']);

    expect($this->get('/Query02?utm_source=wa')->headers->get('Location'))->toBe('https://forms.gle/x?a=1');
});

it('menggabungkan query pada berbagai bentuk tujuan', function (string $tujuan, array $query, string $diharapkan) {
    expect(TeruskanQuery::gabungkan($tujuan, $query))->toBe($diharapkan);
})->with([
    'tanpa query' => ['https://a.com/x', ['k' => 'v'], 'https://a.com/x?k=v'],
    'dengan fragmen' => ['https://a.com/x#f', ['k' => 'v'], 'https://a.com/x?k=v#f'],
    'tidak menimpa' => ['https://a.com/x?k=lama', ['k' => 'baru'], 'https://a.com/x?k=lama'],
    'campuran' => ['https://a.com/x?a=1', ['a' => '2', 'b' => '3'], 'https://a.com/x?a=1&b=3'],
    'kosong' => ['https://a.com/x', [], 'https://a.com/x'],
    'array' => ['https://a.com/x', ['f' => ['1', '2']], 'https://a.com/x?f%5B0%5D=1&f%5B1%5D=2'],
    'karakter khusus' => ['https://a.com/x', ['q' => 'a b&c'], 'https://a.com/x?q=a%20b%26c'],
]);

it('mengabaikan query permintaan bila hasil melebihi 2048 karakter', function () {
    $tujuan = 'https://a.com/'.str_repeat('p', 2000);

    expect(TeruskanQuery::gabungkan($tujuan, ['k' => str_repeat('v', 100)]))->toBe($tujuan);
});
