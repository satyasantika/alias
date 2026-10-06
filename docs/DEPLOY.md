# Panduan Deploy Alias FKIP (F10.3)

Pola docker-apps FKIP. **MySQL tidak dipakai** (keputusan pemilik): basis data SQLite pada volume `alias-data`.
Peralihan ke MySQL 8.4 di kemudian hari: tambahkan service MySQL pada `compose.produksi.yaml`, isi `DB_*` di `.env.production`
dan jalankan migrasi (migrasi sudah memasang `ascii_bin` dan CHECK untuk MySQL).

## 1. Kebutuhan dari UPT TIK (perlu verifikasi)

| Butir | Keterangan |
|---|---|
| DNS | `ALIAS_DOMAIN_PENDEK` (mis. `go.fkip.unsil.ac.id`) dan `ALIAS_DOMAIN_PANEL` (mis. `alias.fkip.unsil.ac.id`) mengarah ke server FKIP |
| TLS | Sertifikat untuk kedua nama (Let's Encrypt/wildcard kampus); HSTS dikirim aplikasi bila HTTPS |
| Reverse proxy | Bila ada proxy di depan server FKIP: isi `TRUSTED_PROXIES` (Laravel) dan `NGINX_REAL_IP_FROM` (nginx) dengan IP/CIDR proxy agar IP klien pada statistik benar |
| Rentang IP NAT | Bila banyak pengguna kampus berbagi satu IP, naikkan `ALIAS_LAJU_PENGALIHAN` (bawaan 300 permintaan/menit/IP) |
| SMTP | Server surel kampus untuk notifikasi; isi `MAIL_*` |

## 2. Pasang

```bash
git clone <repo> alias && cd alias && git checkout v1.0.0
cp .env.production.example .env.production      # isi APP_KEY, MAIL_*, TRUSTED_PROXIES, dll.
docker run --rm php:8.4-cli php -r 'echo "base64:".base64_encode(random_bytes(32)),"\n";'   # nilai APP_KEY
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

- Kesehatan: `GET /up` (nginx), `GET /api/health` (DB, Redis, antrean). Horizon: `/horizon` (super-admin).
- Layanan: `alias-php` (PHP-FPM statik 20 worker, OPcache), `alias-nginx`, `alias-horizon`, `alias-scheduler`, `alias-redis`; semua `restart: unless-stopped` dengan healthcheck.
- Redis tidak perlu dicadangkan (sesi, cache, antrean, garam IP harian).
- Log: `LOG_STACK=daily`; rotasi harian oleh Laravel (14 hari). Log kontainer: batasi lewat `logging` driver Docker (`max-size`/`max-file`) pada daemon.

## 5. Cadangan

Basis data SQLite berada di volume `alias-data` (`/var/www/html/database/data/alias.sqlite`).

```bash
# Harian (cron host), retensi 30 hari — salinan konsisten lewat perintah .backup SQLite
docker compose -f compose.produksi.yaml exec -T alias-php php -r '
$p = new PDO("sqlite:/var/www/html/database/data/alias.sqlite");
$p->exec("VACUUM INTO \"/var/www/html/storage/app/tmp/cadangan-".date("Ymd").".sqlite\"");'
docker cp alias-php:/var/www/html/storage/app/tmp/cadangan-$(date +%Y%m%d).sqlite /backup/alias/
find /backup/alias -name 'cadangan-*.sqlite' -mtime +30 -delete
```

Catatan: `storage/app/tmp` dibersihkan otomatis setelah 24 jam; salin berkas keluar segera. Bila MySQL kelak dipakai, ganti dengan `mysqldump --single-transaction` harian (retensi ≥ 30 hari).

**Uji pulih tiap semester**: hentikan `alias-php`/`alias-horizon`/`alias-scheduler`, salin cadangan ke volume sebagai `alias.sqlite`, jalankan ulang, lalu cek `GET /api/health`, login, dan satu `/kode`.

## 6. Pemeriksaan pasca-deploy

1. `curl -sI https://<domain-pendek>/<kode>` → `302`, `Location`, tanpa `Set-Cookie`.
2. `/panel/login` tampil; admin diminta menyiapkan MFA.
3. `/api/health` → `{"db":"ok","redis":"ok",...}`.
4. Kirim surel uji (permintaan akses) dan pastikan diterima.
5. Jalankan `php artisan schedule:list` dan pastikan 10 jadwal terdaftar.
