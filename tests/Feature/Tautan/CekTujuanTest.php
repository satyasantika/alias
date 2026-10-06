<?php

use App\Enums\StatusCekTujuan;
use App\Events\TujuanBermasalah;
use App\Jobs\PeriksaKesehatanTujuan;
use App\Models\TautanPendek;
use App\Models\User;
use App\Support\Tujuan\PemeriksaTujuan;
use App\Support\Tujuan\ResolverDns;
use Database\Seeders\AturanDomainSeeder;
use Database\Seeders\PengaturanSeeder;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;

function dnsPalsu(array $peta = []): void
{
    app()->instance(ResolverDns::class, new class($peta) extends ResolverDns
    {
        public function __construct(private array $peta) {}

        public function resolve(string $host): array
        {
            return $this->peta[$host] ?? ['93.184.216.34'];
        }
    });
}

beforeEach(function () {
    $this->seed([AturanDomainSeeder::class, PengaturanSeeder::class]);
    Cache::flush();
    dnsPalsu();
    $this->tautan = fn (string $url = 'https://tujuan.contoh.com/halaman') => TautanPendek::factory()->milikPribadi(User::factory()->create())
        ->create(['url_tujuan' => $url, 'host_tujuan' => parse_url($url, PHP_URL_HOST)]);
    $this->cek = fn (TautanPendek $t) => (new PeriksaKesehatanTujuan($t->id))->handle(app(PemeriksaTujuan::class));
});

it('menandai 200 sebagai sehat dan mengisi penanda cek', function () {
    Http::fake(['*' => Http::response('', 200)]);
    $t = ($this->tautan)();

    ($this->cek)($t);

    $t->refresh();
    expect($t->status_cek_tujuan)->toBe(StatusCekTujuan::Sehat)
        ->and($t->kode_http_terakhir)->toBe(200)
        ->and($t->gagal_cek_beruntun)->toBe(0)
        ->and($t->dicek_tujuan_pada)->not->toBeNull();
    Http::assertSent(fn (Request $r) => $r->method() === 'HEAD');
});

it('mencoba GET bila HEAD ditolak 405 atau 501', function (int $kode) {
    Http::fake(fn (Request $r) => $r->method() === 'HEAD' ? Http::response('', $kode) : Http::response('ok', 200));
    $t = ($this->tautan)();

    ($this->cek)($t);

    expect($t->fresh()->status_cek_tujuan)->toBe(StatusCekTujuan::Sehat);
    Http::assertSentCount(2);
})->with([405, 501]);

it('menandai 401/403 sebagai terbatas tanpa menghitung kegagalan', function (int $kode) {
    Http::fake(['*' => Http::response('', $kode)]);
    $t = ($this->tautan)();

    ($this->cek)($t);

    expect($t->fresh()->status_cek_tujuan)->toBe(StatusCekTujuan::Terbatas)->and($t->fresh()->gagal_cek_beruntun)->toBe(0);
})->with([401, 403]);

it('menjadi bermasalah setelah 404 dua kali berturut-turut dan mengirim event sekali', function () {
    Event::fake([TujuanBermasalah::class]);
    Http::fake(['*' => Http::response('', 404)]);
    $t = ($this->tautan)();

    ($this->cek)($t);
    expect($t->fresh()->status_cek_tujuan)->toBe(StatusCekTujuan::Belum)->and($t->fresh()->gagal_cek_beruntun)->toBe(1);
    Event::assertNotDispatched(TujuanBermasalah::class);

    ($this->cek)($t);
    expect($t->fresh()->status_cek_tujuan)->toBe(StatusCekTujuan::Bermasalah)->and($t->fresh()->gagal_cek_beruntun)->toBe(2);
    Event::assertDispatchedTimes(TujuanBermasalah::class, 1);

    ($this->cek)($t);
    Event::assertDispatchedTimes(TujuanBermasalah::class, 1);
});

it('memulihkan status menjadi sehat dan mereset penghitung setelah tujuan pulih', function () {
    Http::fake(['*' => Http::sequence()->push('', 500)->push('', 500)->push('', 200)]);
    $t = ($this->tautan)();

    ($this->cek)($t);
    ($this->cek)($t);
    expect($t->fresh()->status_cek_tujuan)->toBe(StatusCekTujuan::Bermasalah);

    ($this->cek)($t);
    expect($t->fresh()->status_cek_tujuan)->toBe(StatusCekTujuan::Sehat)->and($t->fresh()->gagal_cek_beruntun)->toBe(0);
});

it('menganggap 404, 410, 5xx, dan timeout sebagai kegagalan', function (int|string $respons) {
    Http::fake(['*' => $respons === 'timeout'
        ? fn () => throw new ConnectionException('timeout')
        : Http::response('', $respons)]);
    $t = ($this->tautan)();

    ($this->cek)($t);

    expect($t->fresh()->gagal_cek_beruntun)->toBe(1)
        ->and($t->fresh()->kode_http_terakhir)->toBe($respons === 'timeout' ? 0 : $respons);
})->with([404, 410, 500, 503, 'timeout']);

it('mengikuti redirect hingga 5 hop dan menilai hop terakhir', function () {
    Http::fake(function (Request $r) {
        return match ($r->url()) {
            'https://a.contoh.com/1' => Http::response('', 301, ['Location' => 'https://b.contoh.com/2']),
            'https://b.contoh.com/2' => Http::response('', 302, ['Location' => '/3']),
            'https://b.contoh.com/3' => Http::response('', 200),
            default => Http::response('', 500),
        };
    });
    $t = ($this->tautan)('https://a.contoh.com/1');

    ($this->cek)($t);

    expect($t->fresh()->status_cek_tujuan)->toBe(StatusCekTujuan::Sehat);
});

it('menggagalkan redirect ke-6', function () {
    Http::fake(function (Request $r) {
        $n = (int) substr($r->url(), -1);

        return Http::response('', 302, ['Location' => 'https://h'.($n + 1).'.contoh.com/'.($n + 1)]);
    });
    $t = ($this->tautan)('https://h0.contoh.com/0');

    ($this->cek)($t);

    expect($t->fresh()->gagal_cek_beruntun)->toBe(1);
    Http::assertSentCount(6);
});

it('menghentikan redirect ke IP privat tanpa pernah meminta ke IP itu', function (string $tujuan) {
    Http::fake(function (Request $r) use ($tujuan) {
        return str_contains($r->url(), 'tujuan.contoh.com')
            ? Http::response('', 302, ['Location' => $tujuan])
            : Http::response('', 200);
    });
    $t = ($this->tautan)();

    ($this->cek)($t);

    expect($t->fresh()->gagal_cek_beruntun)->toBe(1)->and($t->fresh()->kode_http_terakhir)->toBe(0);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '10.0.0.1') || str_contains($r->url(), '127.0.0.1') || str_contains($r->url(), '169.254'));
})->with(['http://10.0.0.1/admin', 'http://127.0.0.1:8080/', 'http://169.254.169.254/latest/meta-data', 'http://[::1]/', 'file:///etc/passwd']);

it('menghentikan redirect ke host yang me-resolve ke IP privat (DNS rebinding)', function () {
    dnsPalsu(['jahat.contoh.com' => ['10.1.1.1']]);
    Http::fake(function (Request $r) {
        return str_contains($r->url(), 'tujuan.contoh.com')
            ? Http::response('', 302, ['Location' => 'https://jahat.contoh.com/x'])
            : Http::response('', 200);
    });
    $t = ($this->tautan)();

    ($this->cek)($t);

    expect($t->fresh()->gagal_cek_beruntun)->toBe(1);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'jahat.contoh.com'));
});

it('menolak tujuan yang kini melanggar aturan domain tanpa membuat permintaan', function () {
    Http::fake();
    $t = ($this->tautan)('https://bit.ly/abc');

    ($this->cek)($t);

    expect($t->fresh()->gagal_cek_beruntun)->toBe(1);
    Http::assertNothingSent();
});

it('hanya memakai HEAD pada respons besar sehingga badan tidak diunduh', function () {
    Http::fake(['*' => Http::response(str_repeat('x', 200_000), 200)]);
    $t = ($this->tautan)();

    ($this->cek)($t);

    Http::assertSent(fn (Request $r) => $r->method() === 'HEAD');
    expect($t->fresh()->status_cek_tujuan)->toBe(StatusCekTujuan::Sehat);
});

it('membatasi 2 permintaan per detik per host dan melepas job bila penuh', function () {
    Http::fake(['*' => Http::response('', 200)]);
    RateLimiter::hit('cek-host:tujuan.contoh.com', 60);
    RateLimiter::hit('cek-host:tujuan.contoh.com', 60);
    $t = ($this->tautan)();

    $job = (new PeriksaKesehatanTujuan($t->id))->setJob($fake = Mockery::mock(Job::class)->shouldIgnoreMissing());
    $fake->shouldReceive('release')->once()->with(5);

    $job->handle(app(PemeriksaTujuan::class));

    expect($t->fresh()->dicek_tujuan_pada)->toBeNull();
});

it('tidak memengaruhi pengalihan: tautan bermasalah tetap 302', function () {
    $t = ($this->tautan)('https://tujuan.contoh.com/x');
    $t->forceFill(['status_cek_tujuan' => 'bermasalah', 'gagal_cek_beruntun' => 2])->saveQuietly();

    $this->get('/'.$t->kode)->assertStatus(302)->assertHeader('Location', 'https://tujuan.contoh.com/x');
});

it('mengantre pemeriksaan unik per tautan', function () {
    Queue::fake();
    $t = ($this->tautan)();

    PeriksaKesehatanTujuan::dispatch($t->id);

    Queue::assertPushed(PeriksaKesehatanTujuan::class, fn ($j) => $j->queue === 'cek-tujuan' && $j->uniqueId() === $t->id && $j->timeout === 30 && $j->tries === 2);
});

it('mengantrekan tautan aktif yang belum atau lama tidak dicek lewat perintah', function () {
    Queue::fake();
    $baru = ($this->tautan)();
    $belumPernah = ($this->tautan)();
    $lama = ($this->tautan)();
    $baru->forceFill(['dicek_tujuan_pada' => now()->subDays(2)])->saveQuietly();
    $lama->forceFill(['dicek_tujuan_pada' => now()->subDays(7)])->saveQuietly();
    $nonaktif = ($this->tautan)();
    $nonaktif->forceFill(['status' => 'dinonaktifkan'])->saveQuietly();

    $this->artisan('alias:periksa-tujuan')->expectsOutput('2 pemeriksaan diantrekan.')->assertSuccessful();

    Queue::assertPushed(PeriksaKesehatanTujuan::class, 2);
    Queue::assertPushed(PeriksaKesehatanTujuan::class, fn ($j) => $j->tautanId === $lama->id);
    Queue::assertPushed(PeriksaKesehatanTujuan::class, fn ($j) => $j->tautanId === $belumPernah->id);
});
