# Audit Awal Repo Alias

> F0.1 — audit hanya-baca per 2026-10-06. Tidak memuat nilai rahasia. Rujukan: `vibecoding/02-ARSITEKTUR.md` §2.1 dan §11.

## 1. Versi

| Komponen | Terpasang | Target paket |
|---|---|---|
| PHP (host WSL) | 8.3.6 — tanpa `mbstring`, `pdo_sqlite`, `pdo_mysql`, `intl`, `redis`, `bcmath` | 8.4 di container |
| PHP (runner uji) | 8.4.26 (image lokal `code-keuangan-php`, `--entrypoint php`) | 8.4 |
| laravel/framework | 13.24.0 | 13 |
| filament/filament | 4.11.7 | 5 (atau ADR tetap di 4, F1.3) |
| livewire/livewire | 3.8.1 | 4 (ikut Filament 5) |
| spatie/laravel-permission | 6.25.0 | sesuai |
| bezhansalleh/filament-shield | 4.2.0 | tambahan (tidak ada di paket) |
| stechstudio/filament-impersonate | 4.1.4 | tambahan (tidak ada di paket) |
| PHPUnit | 12.5.33 | Pest 4 belum terpasang |
| Node / Vite / Tailwind | 26.10.0 / 8 / 4 | sesuai |
| Pint | 1.30.4 | sesuai |
| Larastan, Horizon, activitylog, Boost, Pest | belum ada | F1.2 / F1.4 |

## 2. Docker

Repo **belum memiliki** `docker-compose.yml`, `Dockerfile`, maupun container `alias-*`. Docker daemon di WSL aktif (berisi container proyek lain).

## 3. Konfigurasi `.env` (kunci saja)

| Kunci | Kondisi |
|---|---|
| `DB_CONNECTION` | `mysql` (.env.example juga `mysql`, host 127.0.0.1) — MySQL dikecualikan, akan diganti SQLite (F1.1) |
| `CACHE_STORE` / `SESSION_DRIVER` | `database` / `database` (paket: redis) |
| `QUEUE_CONNECTION` | `sync` (paket: redis) |
| `APP_LOCALE` | `id`; `APP_TIMEZONE` belum ada, `config/app.php` timezone `UTC` (paket: `Asia/Jakarta`) |
| `phpunit.xml` | sudah SQLite `:memory:`, cache/session array |

## 4. Yang sudah ada

- Migrasi: users (UUID), cache, jobs (UUID), tabel Spatie permission (UUID).
- Model: `User` (HasUuids, HasRoles, FilamentUser), `Role`, `Permission`. Policy: `UserPolicy`, `RolePolicy`.
- Panel Filament: id `admin`, path `/admin` (paket: id `alias`, path `/panel`), `->login()`, tanpa registrasi, plugin Shield + Impersonate.
- Resource: `UserResource` (List/Create/Edit). Seeder: `RolePermissionSeeder`, `DatabaseSeeder` (Super Admin + demo user).
- Rute aplikasi: `/` dan rute panel `admin/users*`. Test: `ExampleTest` Feature & Unit (hijau, 2 test).
- Git: branch `main`, tanpa remote, tanpa `.githooks`, tanpa CI, tanpa `CLAUDE.md` di root, tanpa `docs/`.

## 5. Kesenjangan terhadap 02-ARSITEKTUR

| Butir | Status | Tindakan |
|---|---|---|
| Layanan Docker (php, nginx, redis, horizon, scheduler, mailpit) | belum ada | F1.1 — **MySQL dikecualikan** |
| Basis data | MySQL di `.env`, uji SQLite | pakai SQLite; lihat risiko 1 |
| Redis (sesi, cache, antrean) | belum | F1.1–F1.2 |
| Pest 4, Larastan, skrip `composer cek` | belum | F1.2 |
| Locale/timezone Indonesia | locale `id`, timezone UTC | F1.2 |
| Filament 4 → 5 | terpasang 4.11.7 | F1.3 |
| Horizon, activitylog, Boost, `/api/health` | belum | F1.4 |
| UUIDv7 | `HasUuids` sudah dipakai di users/jobs/permission | F1.5 tinggal verifikasi + `UuidTest` |
| CI | belum | F1.6 |
| Panel `alias` di `/panel` | panel `admin` di `/admin` | F2.3 |
| Hook git, `.gitignore` tambahan, dokumen | belum | F0.2 |

## 6. Risiko

1. **MySQL dikecualikan**: paket mengasumsikan MySQL (`ascii_bin`, CHECK constraint, kunci `CHAR(36)`). SQLite tidak menegakkan collation `ascii_bin` (kode `Abc1234` vs `abc1234` perlu perbandingan biner) dan CHECK berbeda sedikit. Langkah F4.1 harus menyesuaikan (kolom `COLLATE BINARY`) dan uji paritas dicatat.
2. Host PHP tidak lengkap; semua perintah PHP dijalankan di container sekali pakai.
3. Tanpa Redis, rate limit, penghitung login, dan garam HMAC harian (BR-16/BR-34) memakai driver cache lain sampai Redis tersedia.
4. Filament 5 mungkin terblokir plugin Shield/Impersonate (F1.3 menentukan).
5. Panel `/admin` ada di repo, rancangan memakai `/panel`: ganti path pada F2.3 bisa memutus bookmark/test yang ada.
6. Tanpa remote git: langkah PR/tag (`git push`) tidak dapat dijalankan; merge dilakukan lokal.
