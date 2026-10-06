<?php

use App\Enums\JenisPerangkat;
use App\Jobs\CatatKunjungan;
use App\Models\KunjunganTautan;
use App\Models\TautanPendek;
use App\Models\User;
use App\Support\Kunjungan\AnonimisasiIp;
use App\Support\Kunjungan\DeteksiBotCepat;
use App\Support\Kunjungan\GaramHarian;
use App\Support\Kunjungan\HostPerujuk;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

const UA_CHROME = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';
const UA_PONSEL = 'Mozilla/5.0 (Linux; Android 13; SM-S918B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36';
const UA_WA = 'WhatsApp/2.23.20.0 A';

beforeEach(function () {
    Cache::flush();
    $this->pemilik = User::factory()->create();
    $this->tautan = TautanPendek::factory()->milikPribadi($this->pemilik)->create(['kode' => 'Kunj001', 'url_tujuan' => 'https://forms.gle/x', 'host_tujuan' => 'forms.gle']);
    $this->klik = fn (string $ip = '103.21.44.17', string $ua = UA_CHROME, array $header = [], string $kode = 'Kunj001') => $this
        ->withServerVariables(['REMOTE_ADDR' => $ip])->withHeaders(['User-Agent' => $ua, ...$header])->get('/'.$kode);
});

it('menganonimkan IPv4 dan IPv6', function (string $ip, string $diharapkan) {
    expect(AnonimisasiIp::anonimkan($ip))->toBe($diharapkan);
})->with([
    ['103.21.44.17', '103.21.44.0'],
    ['8.8.8.8', '8.8.8.0'],
    ['2001:db8:1234:5678:9abc:def0:1234:5678', '2001:db8:1234::'],
    ['::ffff:103.21.44.17', '103.21.44.0'],
    ['bukan-ip', '0.0.0.0'],
]);

it('membuat ip_hash berbeda untuk hari berbeda dan konsisten pada hari yang sama', function () {
    $a = GaramHarian::hashIp('103.21.44.17');
    $b = GaramHarian::hashIp('103.21.44.17');
    $lain = GaramHarian::hashIp('103.21.44.18');

    $this->travel(1)->day();
    $besok = GaramHarian::hashIp('103.21.44.17');

    expect($a)->toBe($b)->toHaveLength(64)->and($lain)->not->toBe($a)->and($besok)->not->toBe($a);
});

it('menyimpan garam harian hanya di cache dengan TTL 48 jam dan tidak di basis data', function () {
    GaramHarian::hashIp('1.2.3.4');
    $garam = Cache::get(GaramHarian::kunci());

    expect($garam)->toHaveLength(64);

    foreach (DB::select("select name from sqlite_master where type='table'") as $tabel) {
        foreach (DB::table($tabel->name)->get() as $baris) {
            expect(json_encode($baris))->not->toContain($garam);
        }
    }
});

it('mengambil host perujuk saja', function () {
    expect(HostPerujuk::dari('https://WWW.Contoh.com/path/rahasia?token=1#x'))->toBe('www.contoh.com')
        ->and(HostPerujuk::dari('javascript:alert(1)'))->toBeNull()
        ->and(HostPerujuk::dari(''))->toBeNull()
        ->and(HostPerujuk::dari(null))->toBeNull();
});

it('mendeteksi bot cepat', function (?string $ua, bool $bot) {
    expect(DeteksiBotCepat::apakahBot($ua))->toBe($bot);
})->with([
    [UA_CHROME, false], [UA_PONSEL, false], [UA_WA, true], ['TelegramBot (like TwitterBot)', true],
    ['facebookexternalhit/1.1', true], ['Slackbot-LinkExpanding 1.0', true], ['Discordbot/2.0', true], ['curl/8.4.0', true],
    ['python-requests/2.31', true], ['Go-http-client/1.1', true], ['Mozilla/5.0 HeadlessChrome/120', true], ['', true], [null, true],
]);

it('mencatat kunjungan manusia: IP anonim, hash, peramban, OS, perangkat, perujuk', function () {
    ($this->klik)('103.21.44.17', UA_CHROME, ['Referer' => 'https://www.facebook.com/groups/abc?x=1'])->assertStatus(302);

    $k = KunjunganTautan::firstOrFail();
    expect($k->ip_anonim)->toBe('103.21.44.0')
        ->and($k->ip_hash)->toHaveLength(64)
        ->and($k->peramban)->toBe('Chrome')->and($k->versi_peramban)->toBe('124')
        ->and($k->os)->toBe('Windows')
        ->and($k->jenis_perangkat)->toBe(JenisPerangkat::Desktop)
        ->and($k->perujuk_host)->toBe('www.facebook.com')
        ->and($k->bot)->toBeFalse()
        ->and($this->tautan->fresh()->jumlah_klik)->toBe(1)
        ->and($this->tautan->fresh()->klik_terakhir_pada)->not->toBeNull();
});

it('mengenali ponsel', function () {
    ($this->klik)('103.21.44.17', UA_PONSEL);

    expect(KunjunganTautan::firstOrFail()->jenis_perangkat)->toBe(JenisPerangkat::Ponsel);
});

it('mencatat bot WhatsApp sebagai bot dan tidak menaikkan jumlah_klik', function () {
    ($this->klik)('157.240.1.1', UA_WA)->assertStatus(302);

    $k = KunjunganTautan::firstOrFail();
    expect($k->bot)->toBeTrue()->and($k->jenis_perangkat)->toBe(JenisPerangkat::Bot)->and($k->nama_bot)->not->toBeNull()
        ->and($this->tautan->fresh()->jumlah_klik)->toBe(0);
});

it('tidak pernah menyimpan IP utuh maupun user agent mentah di basis data atau log', function () {
    Log::spy();
    ($this->klik)('103.21.44.17', UA_CHROME)->assertStatus(302);

    foreach (DB::select("select name from sqlite_master where type='table'") as $tabel) {
        foreach (DB::table($tabel->name)->get() as $baris) {
            $json = json_encode($baris);
            expect($json)->not->toContain('103.21.44.17')->not->toContain('AppleWebKit');
        }
    }
    Log::shouldNotHaveReceived('warning');
});

it('tidak memasukkan IP utuh ke payload antrean', function () {
    Queue::fake();
    ($this->klik)('103.21.44.17', UA_CHROME)->assertStatus(302);

    Queue::assertPushed(CatatKunjungan::class, function (CatatKunjungan $job) {
        $payload = json_encode((array) $job);

        return ! str_contains($payload, '103.21.44.17') && $job->ipAnonim === '103.21.44.0' && $job->queue === 'kunjungan';
    });
});

it('tidak mencatat kunjungan untuk HEAD', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '103.21.44.17'])->call('HEAD', '/Kunj001')->assertStatus(302);

    expect(KunjunganTautan::count())->toBe(0)->and($this->tautan->fresh()->jumlah_klik)->toBe(0);
});

it('hanya menaikkan penghitung bila catat_kunjungan nonaktif', function () {
    $this->tautan->forceFill(['catat_kunjungan' => false])->saveQuietly();

    ($this->klik)('103.21.44.17', UA_CHROME)->assertStatus(302);

    expect(KunjunganTautan::count())->toBe(0)->and($this->tautan->fresh()->jumlah_klik)->toBe(1);
});

it('tetap mengalihkan walau antrean/Redis gagal dan hanya mencatat peringatan tanpa IP', function () {
    Log::spy();
    $this->mock(Dispatcher::class)->shouldReceive('dispatch')->andThrow(new RuntimeException('Connection refused 103.21.44.17'));

    ($this->klik)('103.21.44.17', UA_CHROME)->assertStatus(302)->assertHeader('Location', 'https://forms.gle/x');

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $pesan, array $konteks) => ! str_contains(json_encode($konteks), '103.21.44.17'));
});

it('memakai IP klien dari proksi tepercaya', function () {
    config(['alias.proksi_tepercaya' => '*']);
    TrustProxies::at('*');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->withHeaders(['X-Forwarded-For' => '103.21.44.17', 'User-Agent' => UA_CHROME])->get('/Kunj001')->assertStatus(302);

    expect(KunjunganTautan::firstOrFail()->ip_anonim)->toBe('103.21.44.0');
});

afterEach(fn () => TrustProxies::flushState());
