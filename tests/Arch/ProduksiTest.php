<?php

use Symfony\Component\Yaml\Yaml;

/**
 * Produksi Profil Ringan (STANDAR-TEKNIS §1b dan §7): MariaDB NATIVE di host; compose tanpa container database,
 * Redis, atau Horizon; setiap container berbatas memori dan lognya dirotasi.
 */
it('mendefinisikan layanan produksi hanya php, nginx, dan scheduler, dengan healthcheck dan restart', function () {
    $compose = Yaml::parseFile(base_path('compose.produksi.yaml'));
    $layanan = $compose['services'];

    expect(array_keys($layanan))->toEqualCanonicalizing(['alias-php', 'alias-nginx', 'alias-scheduler']);

    foreach (['db', 'mysql', 'mariadb', 'redis', 'alias-redis', 'alias-horizon', 'queue', 'meilisearch'] as $dilarang) {
        expect($layanan)->not->toHaveKey($dilarang);
    }

    // hasil parse (tanpa komentar): tidak ada image/perintah database, Redis, atau Horizon, dan tidak ada volume SQLite
    expect(json_encode($compose))->not->toMatch('/horizon|redis|mysql:|mariadb:|sqlite/i')
        ->and($compose['volumes'] ?? [])->not->toHaveKey('alias-data');

    foreach ($layanan as $nama => $konfigurasi) {
        if ($nama !== 'alias-scheduler') {
            expect(array_key_exists('healthcheck', $konfigurasi))->toBeTrue("{$nama} tanpa healthcheck");
        }
        expect(($konfigurasi['restart'] ?? $compose['x-app']['restart'] ?? null))->toBe('unless-stopped', $nama)
            ->and(array_key_exists('mem_limit', $konfigurasi))->toBeTrue("{$nama} tanpa mem_limit");
        expect($konfigurasi['logging']['driver'] ?? $compose['x-log']['driver'])->toBe('json-file');
    }
});

it('membangun alias-nginx dari image sendiri (target web), bukan volume bersama yang bisa basi', function () {
    $compose = Yaml::parseFile(base_path('compose.produksi.yaml'));
    $nginx = $compose['services']['alias-nginx'];
    $php = $compose['services']['alias-php'];

    // public/ di-bake ke image saat build (COPY --from=app), bukan ditulis sekali ke volume kosong lalu
    // tidak pernah disegarkan lagi -- volume bernama hanya cocok untuk storage/ yang memang perlu persisten.
    expect($nginx['build']['target'])->toBe('web')
        ->and($nginx)->not->toHaveKey('volumes')
        ->and($compose['volumes'] ?? [])->not->toHaveKey('alias-public');

    // Dockerfile berakhir di stage 'web' (ditambahkan setelah 'app'); tanpa target: eksplisit di sini,
    // build alias-php akan diam-diam membangun stage TERAKHIR (nginx), bukan php-fpm -- lihat insiden cutover.
    expect($php['build']['target'])->toBe('app');
});

it('menghubungkan container PHP dan scheduler ke MariaDB host lewat host-gateway', function () {
    $compose = Yaml::parseFile(base_path('compose.produksi.yaml'));

    foreach (['alias-php', 'alias-scheduler'] as $nama) {
        $hosts = $compose['services'][$nama]['extra_hosts'] ?? $compose['x-app']['extra_hosts'] ?? [];
        expect($hosts)->toContain('host.docker.internal:host-gateway');
    }

    expect(file_get_contents(base_path('.env.production.example')))->toContain("\nDB_HOST=host.docker.internal\n");
});

it('menjalankan penjadwal sebagai pemicu worker tanpa bergantung pada layanan lain', function () {
    $penjadwal = Yaml::parseFile(base_path('compose.produksi.yaml'))['services']['alias-scheduler'];

    expect($penjadwal['command'])->toBe(['php', 'artisan', 'schedule:work'])
        ->and($penjadwal)->not->toHaveKey('depends_on')
        ->and($penjadwal['stop_grace_period'])->toBe('60s');
});

it('menjaga compose lokal tanpa Redis dan Horizon, dengan extra_hosts ke MariaDB host', function () {
    $compose = Yaml::parseFile(base_path('docker-compose.yml'));

    expect(array_keys($compose['services']))->toEqualCanonicalizing(['alias-mailpit', 'alias-scheduler'])
        ->and(json_encode($compose))->not->toMatch('/horizon|redis|sqlite/i')
        ->and($compose['x-app']['extra_hosts'])->toContain('host.docker.internal:host-gateway');
});

it('menjaga nginx produksi: tanpa cache proxy, real_ip, batas unggahan 2M, aset ber-cache saja', function () {
    $konf = file_get_contents(base_path('docker/nginx/produksi.conf.template'));

    expect($konf)->toContain('set_real_ip_from ${NGINX_REAL_IP_FROM}')->toContain('client_max_body_size 2M')
        ->toContain('proxy_cache off')->toContain('gzip on')->toContain('server_tokens off')
        ->and($konf)->not->toContain('proxy_cache_path')->not->toContain('fastcgi_cache_path');
});

it('tidak memuat rahasia pada contoh env produksi dan menutup mode debug', function () {
    $env = collect(file(base_path('.env.production.example'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
        ->reject(fn ($b) => str_starts_with($b, '#'))
        ->mapWithKeys(function ($baris) {
            [$k, $v] = array_pad(explode('=', $baris, 2), 2, '');

            return [$k => $v];
        });

    expect($env['APP_DEBUG'])->toBe('false')->and($env['APP_ENV'])->toBe('production')->and($env['APP_KEY'])->toBe('')
        ->and($env['MAIL_PASSWORD'])->toBe('')->and($env['GOOGLE_CLIENT_SECRET'])->toBe('')->and($env['SEED_PASSWORD'])->toBe('')
        ->and($env['SESSION_SECURE_COOKIE'])->toBe('true')->and($env['CACHE_STORE'])->toBe('database')->and($env['ALIAS_CACHE_LOOKUP'])->toBe('false');
});

it('menyiapkan Dockerfile bertahap tanpa dev dependency dan OPcache produksi', function () {
    $docker = file_get_contents(base_path('docker/php/Dockerfile'));

    expect($docker)->toContain('composer install --no-dev')->toContain('npm run build')->toContain('USER www-data')
        ->and(file_get_contents(base_path('docker/php/opcache.ini')))->toContain('opcache.validate_timestamps=0')
        ->and(file_get_contents(base_path('docker/php/fpm-pool.conf')))->toMatch('/^pm = ondemand$/m')->toMatch('/^pm\.max_children = 4$/m')->toMatch('/^pm\.process_idle_timeout = 10s$/m')
        ->and(file_get_contents(base_path('.dockerignore')))->toContain('.env')->toContain('bootstrap/cache/*.php');
});

it('membangun citra produksi dari PHP 8.5 tanpa Redis dan SQLite', function () {
    $docker = file_get_contents(base_path('docker/php/Dockerfile'));

    expect($docker)->toContain('FROM php:8.5-fpm')->toContain('pdo_mysql')
        ->not->toMatch('/redis|horizon|pdo_sqlite/i');
});

it('tidak melacak berkas rahasia di git', function () {
    $terlacak = shell_exec('git ls-files 2>/dev/null') ?? '';

    foreach (['.env', '.env.production', 'auth.json'] as $berkas) {
        expect(preg_match('/^'.preg_quote($berkas, '/').'$/m', $terlacak))->toBe(0, $berkas);
    }
    expect($terlacak)->not->toMatch('/\.sqlite/')->not->toMatch('/\.sql(\.gz)?$/m');
});
