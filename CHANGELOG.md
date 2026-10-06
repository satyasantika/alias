# Catatan Perubahan

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan [SemVer](https://semver.org/lang/id/).

## [Belum dirilis]

## [0.3.0] - 2026-10-07

### Ditambahkan
- Unit (hierarki prodi/jurusan/fakultas) dan keanggotaan pengelola/anggota, dengan pengaman pengelola terakhir dan sinkron peran `pengelola-unit`.
- Manajemen pengguna (`PenggunaResource`): buat akun tanpa kata sandi + surel atur kata sandi, kuota, nonaktifkan dengan opsi pengalihan tautan, batasan peran admin (BR-33).
- Permintaan akses publik `/minta-akses` (honeypot, throttle 3/jam, verifikasi surel 24 jam) dan antrean persetujuan di panel.
- Terjemahan Indonesia (`lang/id`), batas laju bernama dari `config/alias.php`, garam IP harian.

## [0.2.0] - 2026-10-06

### Ditambahkan
- Perluasan tabel `users` (NIP/NIDN, unit, aktif, kuota, penguncian, Google, kolom MFA, soft delete) dan aturan domain surel `@unsil.ac.id`.
- Lima peran dan 30 permission (matriks PRD §4.3) lewat `PeranDanIzinSeeder`, `Gate::before` super-admin, gerbang Horizon `horizon.lihat`, `PenggunaAwalSeeder` (kata sandi dari `SEED_PASSWORD`).
- Panel `alias` di `/panel`: login berbahasa Indonesia, tanpa registrasi, reset kata sandi, MFA aplikasi autentikator (wajib untuk super-admin & admin-alias), lonceng notifikasi.
- Penguncian akun (10 gagal/30 menit → 15 menit), log login (`log_login`) dan resource penampilnya, penutupan sesi akun nonaktif/terkunci.
- Jejak audit (`TercatatAktivitas`) dan penampil log aktivitas; login Google opsional (tidak membuat akun, tidak untuk peran MFA).

### Diubah
- Panel `admin` (`/admin`) dipindah menjadi panel `alias` (`/panel`); akun contoh kini `*@unsil.ac.id`.

### Dihapus
- `bezhansalleh/filament-shield` (Policy dan seeder ditulis eksplisit sesuai rancangan).

## [0.1.0] - 2026-10-06

### Ditambahkan
- Layanan Docker: Redis, Mailpit, Horizon, scheduler (MySQL dikecualikan; basis data SQLite).
- Lokal/zona waktu Indonesia, Redis untuk sesi/cache/antrean, Pest 4, Larastan level 6, Pint, uji asap.
- Filament 5.9 / Livewire 4, Horizon, activitylog (UUID), Laravel Boost (MCP), endpoint `/api/health`.
- Uji arsitektur kunci primer UUIDv7, workflow CI.

### Ditambahkan
- Audit awal repositori (`docs/AUDIT-AWAL.md`).
- Hook git Conventional Commits dan pra-commit (`.githooks/`), pembungkus PHP container `bin/ap`.
- Aturan `.gitignore` tambahan dan bagian "Pengembangan" pada README.
