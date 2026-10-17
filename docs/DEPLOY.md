# Panduan Deploy Alias FKIP (F10.3)

> **Profil Ringan berlaku setelah Fase B; kondisi saat ini:** panduan ini menggambarkan kode berjalan (SQLite, Redis, `alias-horizon`, `alias-redis`). Tanda **[Setelah Fase B]** menandai ketentuan Profil Ringan (STANDAR-TEKNIS §1b): tanpa Redis/Horizon/Octane; antrean, cache, sesi, dan lock di MariaDB (driver `database`); worker antrean dinyalakan penjadwal `alias-scheduler`; PHP-FPM `pm = ondemand`. Keputusan pemilik 16 Oktober 2026 (PHP 8.5; MariaDB 10.11+ native di host; tanpa SQLite) **berlaku setelah Fase B; kondisi saat ini:** produksi masih SQLite dan PHP 8.4. Lihat catatan di akhir berkas.

Pola docker-apps FKIP. Keputusan pemilik 16 Oktober 2026: basis data = MariaDB 10.11+ native di host (bukan container; tanpa SQLite). **Berlaku setelah Fase B; kondisi saat ini:** basis data SQLite pada volume `alias-data`.
Migrasi data SQLite → MariaDB adalah butir Fase B (`DB_CONNECTION=mariadb`, `DB_HOST=host.docker.internal`, `DB_DATABASE=db_alias`, `DB_USERNAME=alias` di `.env.production`; migrasi memasang `ascii_bin` dan CHECK; tanpa prosedur tertulis). Data lama berformat UUID valid sehingga dapat masuk kolom `UUID` native MariaDB (`uuid()`/`foreignUuid()`/`uuidMorphs()` pada Laravel 13). `compose.produksi.yaml` pada Fase B mengikuti pola `alias-php`/`alias-nginx` di `/home/satya/code/docker-compose.prod.yml`: `extra_hosts: ["host.docker.internal:host-gateway"]`, **tanpa container database** (juga tanpa `alias-redis` dan `alias-horizon`); CI boleh memakai service container MariaDB sementara. Image produksi PHP 8.5 (Dockerfile masih `php:8.4-fpm-alpine`, `composer.json` `^8.3`: butir Fase B).

## 1. Kebutuhan dari UPT TIK (perlu verifikasi)

| Butir | Keterangan |
|---|---|
| DNS | `ALIAS_DOMAIN_PENDEK` (mis. `go.fkip.unsil.ac.id`) dan `ALIAS_DOMAIN_PANEL` (mis. `alias.fkip.unsil.ac.id`) mengarah ke server FKIP |
| TLS | Sertifikat untuk kedua nama (Let's Encrypt/wildcard kampus); HSTS dikirim aplikasi bila HTTPS |
| Reverse proxy | Bila ada proxy di depan server FKIP: isi `TRUSTED_PROXIES` (Laravel) dan `NGINX_REAL_IP_FROM` (nginx) dengan IP/CIDR proxy agar IP klien pada statistik benar |
| Rentang IP NAT | Bila banyak pengguna kampus berbagi satu IP, naikkan `ALIAS_LAJU_PENGALIHAN` (bawaan 300 permintaan/menit/IP) |
| SMTP | Server surel kampus untuk notifikasi; isi `MAIL_*` |

## 1a. Pemasangan di sub-path `https://supportfkip.unsil.ac.id/alias`

Aplikasi siap dipasang di bawah `/alias` pada situs dukungan FKIP (mode satu domain):

- `.env.production`: `APP_URL=https://supportfkip.unsil.ac.id/alias`, `ALIAS_BASE_PATH=/alias`, `SESSION_PATH=/alias`, `ALIAS_DOMAIN_PENDEK`/`ALIAS_DOMAIN_PANEL` kosong. URL pendek menjadi `https://supportfkip.unsil.ac.id/alias/<kode>`; panel di `/alias/panel`; panduan di `/alias/panduan/`.
- Semua URL yang dibangkitkan (rute, aset, Livewire, QR, URL pendek) memakai root `APP_URL` sehingga selalu berawalan `/alias`.
- Reverse proxy situs dukungan boleh **membuang** awalan (disarankan) atau **meneruskannya apa adanya**; keduanya didukung (`public/index.php` dan nginx kontainer membuang awalan bila ada):

```nginx
# di server https://supportfkip.unsil.ac.id
location /alias/ {
    proxy_pass http://<server-alias>:8018/;          # akhiran "/" membuang /alias
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto https;
}
location = /alias { return 301 /alias/; }
```
- Isi `TRUSTED_PROXIES` dan `NGINX_REAL_IP_FROM` dengan IP proxy situs dukungan agar IP klien benar.
- Panduan per peran berupa berkas HTML mandiri di `public/panduan/` (ditaut dari beranda).

## 2. Pasang

```bash
git clone <repo> alias && cd alias && git checkout v1.0.0
cp .env.production.example .env.production      # isi APP_KEY, MAIL_*, TRUSTED_PROXIES, dll.
docker run --rm php:8.5-cli php -r 'echo "base64:".base64_encode(random_bytes(32)),"\n";'   # nilai APP_KEY
ALIAS_MIGRASI=1 docker compose -f compose.produksi.yaml up -d --build     # build + migrasi pertama
docker compose -f compose.produksi.yaml exec alias-php php artisan db:seed --class=PeranDanIzinSeeder --force
docker compose -f compose.produksi.yaml exec alias-php php artisan db:seed --class=UnitSeeder --force
docker compose -f compose.produksi.yaml exec alias-php php artisan db:seed --class=SlugTerlarangSeeder --force
docker compose -f compose.produksi.yaml exec alias-php php artisan db:seed --class=AturanDomainSeeder --force
docker compose -f compose.produksi.yaml exec alias-php php artisan db:seed --class=PengaturanSeeder --force
```

Buat akun super-admin pertama (dua orang, keduanya wajib MFA; lihat checklist G-08):

```bash
docker compose -f compose.produksi.yaml exec alias-php php artisan tinker --execute='
$u = App\Models\User::create(["name"=>"Nama Lengkap","email"=>"nama@unsil.ac.id","password"=>"GANTI-SEGERA","aktif"=>true]);
$u->forceFill(["email_verified_at"=>now()])->save(); $u->assignRole("super-admin");'
```

Login pertama memaksa penyiapan MFA. Segera ganti kata sandi melalui profil.

`PenggunaAwalSeeder` **tidak** berjalan di produksi (hanya local/staging).

## 3. Rilis berikutnya

```bash
git pull --tags && git checkout vX.Y.Z
ALIAS_MIGRASI=1 docker compose -f compose.produksi.yaml up -d --build
```

`alias-php` menjalankan `php artisan optimize` dan `filament:optimize` saat start. Tanpa Docker: `bin/build-produksi [--migrate]`.

## 4. Operasi

- Kesehatan (saat ini): `GET /up` (nginx), `GET /api/health` (DB, Redis, antrean). Horizon: `/horizon` (super-admin).
- Layanan (saat ini): `alias-php` (PHP-FPM statik 20 worker, OPcache), `alias-nginx`, `alias-horizon`, `alias-scheduler`, `alias-redis`; semua `restart: unless-stopped` dengan healthcheck.
- Redis tidak perlu dicadangkan (sesi, cache, antrean, garam IP harian).
- **[Setelah Fase B]** Kesehatan: `GET /up` dan `GET /api/health` (status DB, job tertunda > 10 menit, `failed_jobs` 24 jam terakhir, detak penjadwal; peringatan bila > 5 menit). Tidak ada `/horizon`. Layanan: `alias-php` (PHP-FPM `pm = ondemand`, `max_children = 4`), `alias-nginx`, `alias-scheduler` (`schedule:work`, sekaligus worker antrean `default` dan `berat`); tanpa `alias-horizon` dan `alias-redis`. Job gagal ditangani dengan `php artisan queue:failed` dan `queue:retry <id>`. Cache, sesi, antrean, dan garam IP harian ikut dicadangkan bersama basis data (data sementara, tanpa pemulihan khusus).
- Log: `LOG_STACK=daily`; rotasi harian oleh Laravel (14 hari). Log kontainer: batasi lewat `logging` driver Docker (`max-size`/`max-file`) pada daemon.

## 5. Cadangan

**Kondisi saat ini (SQLite; berlaku sampai Fase B):** basis data SQLite berada di volume `alias-data` (`/var/www/html/database/data/alias.sqlite`).

```bash
# Harian (cron host), retensi 30 hari — salinan konsisten lewat perintah .backup SQLite
docker compose -f compose.produksi.yaml exec -T alias-php php -r '
$p = new PDO("sqlite:/var/www/html/database/data/alias.sqlite");
$p->exec("VACUUM INTO \"/var/www/html/storage/app/tmp/cadangan-".date("Ymd").".sqlite\"");'
docker cp alias-php:/var/www/html/storage/app/tmp/cadangan-$(date +%Y%m%d).sqlite /backup/alias/
find /backup/alias -name 'cadangan-*.sqlite' -mtime +30 -delete
```

Catatan: `storage/app/tmp` dibersihkan otomatis setelah 24 jam; salin berkas keluar segera. Setelah Fase B (MariaDB), ganti dengan dump MariaDB harian yang konsisten dengan `mariadb-dump --single-transaction db_alias` (dijalankan terhadap MariaDB native di host; retensi ≥ 30 hari; cadangan tidak disimpan di disk server yang sama, STANDAR-TEKNIS §7.1). Uji pulih memuat dump ke `db_alias` lalu cek `GET /api/health`.

**Uji pulih tiap semester**: hentikan `alias-php`/`alias-horizon`/`alias-scheduler` (setelah Fase B: `alias-php`/`alias-scheduler`), salin cadangan ke volume sebagai `alias.sqlite`, jalankan ulang, lalu cek `GET /api/health`, login, dan satu `/kode`.

## 6. Pemeriksaan pasca-deploy

1. `curl -sI https://<domain-pendek>/<kode>` → `302`, `Location`, tanpa `Set-Cookie`.
2. `/panel/login` tampil; admin diminta menyiapkan MFA.
3. `/api/health` → `{"db":"ok","redis":"ok",...}` (setelah Fase B: tanpa kunci `redis`; periksa `db`, job tertunda, `failed_jobs`, dan detak penjadwal).
4. Kirim surel uji (permintaan akses) dan pastikan diterima.
5. Jalankan `php artisan schedule:list` dan pastikan 10 jadwal terdaftar.

## Catatan untuk keputusan pemilik

- Keputusan pemilik 16 Oktober 2026: basis data produksi = MariaDB 10.11+ (Profil Ringan bertumpu padanya: antrean, cache, sesi, lock di tabel `jobs`, `cache`, `sessions`). Penyimpangan "MySQL tidak dipakai; SQLite" dicabut. **Butir Fase B:** migrasi data SQLite → MariaDB pada produksi (prosedur belum ditulis); bagian Cadangan (§5) masih berlaku untuk SQLite sampai Fase B.
- Rekomendasi F10.3 (`pm = static`, 20 worker) bertentangan dengan Profil Ringan (§1b: `pm = ondemand`, `max_children = 4`). **Profil Ringan menggantikan; uji beban G-10a diulang** (`docs/BEBAN.md`). Catatan historis F10.3 dibiarkan.
