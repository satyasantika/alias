# Catatan Perubahan

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan [SemVer](https://semver.org/lang/id/).

## [Belum dirilis]

### Diubah
- Panduan super admin tidak lagi berupa berkas publik: disajikan di `/panel/panduan-super-admin` (hanya super admin berMFA; menu "Panduan super admin" di grup Sistem).

## [Belum dirilis]

### Ditambahkan
- Pemasangan di sub-path `https://supportfkip.unsil.ac.id/alias` (APP_URL berjalur, awalan dibuang di `public/index.php`/nginx, semua URL berawalan `/alias`).
- Panduan HTML mandiri per peran dengan tangkapan layar nyata di `public/panduan/` (ditaut dari beranda); sumber & pembangun di `tools/panduan/`; `DemoSeeder`.
- Halaman *lihat* unit sehingga pengelola unit dapat mengelola anggota unitnya (sebelumnya 403).

## [1.0.0] - 2026-10-07

Rilis pertama Alias FKIP: pemendek tautan kelembagaan dengan pengalihan 302, kepemilikan pribadi/unit, moderasi, statistik anonim, dan lima peran.

### Ringkasan fase
- F1 fondasi (Filament 5, Pest/Larastan, Horizon, health); F2 login & peran (MFA admin, penguncian, audit); F3 unit, pengguna, permintaan akses.
- F4 tautan (slug, validasi tujuan anti-SSRF, kuota, status, transfer); F5 pengalihan, pratinjau, kunjungan anonim, sekali pakai/batas klik atomik.
- F6 moderasi & cek kesehatan tujuan; F7 namespace unit, pindai ulang aturan, kata sandi tautan, impor CSV.
- F8 notifikasi & penjadwal; F9 statistik, dasbor per peran, ekspor, pengaturan sistem.

### F10 — pengerasan & rilis
- Uji keamanan menyeluruh (matriks akses PRD §4.3, SSRF, open redirect, header, XSS, tanpa unggahan) dan header keamanan (X-Frame-Options, CSP, HSTS) pada halaman publik; `docs/KEAMANAN.md`.
- Uji beban 50.000 tautan / 1 juta kunjungan: p95 27–34 ms pada 50 rps; **cache lookup tetap nonaktif**; `docs/BEBAN.md`.
- Konfigurasi produksi (`compose.produksi.yaml`, Dockerfile bertahap, nginx, `.env.production.example`, `bin/build-produksi`), panduan deploy & cadangan.
- `alias:siapkan-uat`, panduan pengguna & admin.

### Diperbaiki
- `alias-horizon` pada compose pengembangan kini benar-benar menjalankan Horizon.
- `package-lock.json` dibuat agar `npm ci` pada CI/Docker berjalan; `npm audit` bersih.

### Catatan rilis
- **MySQL dikecualikan** (keputusan pemilik): SQLite; versi Filament **5.9**; **ALIAS_CACHE_LOOKUP=false**.
- Butir yang menunggu keputusan pemilik/UPT TIK dan UAT dengan pengguna nyata dicatat di `docs/KEPUTUSAN-RILIS.md`.

## [0.9.0] - 2026-10-07

### Ditambahkan
- Statistik per tautan (halaman lihat): klik harian 30/90 hari, perangkat, perujuk teratas dari tabel rekap + data mentah hari yang belum direkap; relation manager kunjungan anonim (`kunjungan.lihat-rinci`).
- Dasbor per peran (ringkasan tautan saya, klik per unit, 10 tautan teratas, antrean moderasi) dan halaman Statistik fakultas dengan filter bulan; cakupan pengelola hanya unitnya.
- Ekspor berantrean (rekap tautan, rekap unit, kunjungan anonim, rekap moderasi XLSX) ke disk privat `tmp` (dibersihkan 24 jam), tabel ekspor ber-UUID, tanpa `ip_hash`/kata sandi.
- Halaman Pengaturan sistem (super-admin) untuk kuota, persetujuan slug, mode domain, retensi, ambang, dan teks privasi tanpa deploy.

## [0.8.0] - 2026-10-07

### Ditambahkan
- Seluruh notifikasi 02 §9 (surel + lonceng panel, antrean `notifikasi`), dikirim lewat event `ShouldDispatchAfterCommit` sehingga transaksi gagal tidak mengirim apa pun; hanya pengguna aktif; tautan unit → semua pengelola; pemindahan massal → satu ringkasan.
- Tabel rekap kunjungan harian/dimensi, perintah `alias:rekap-kunjungan` (idempoten, berlock), `alias:pangkas-kunjungan` (hanya tanggal yang sudah direkap), `alias:tolak-kedaluwarsa`, `alias:ingatkan-kedaluwarsa`, `alias:laporan-yatim`, `alias:pangkas-log-login`, `alias:bersihkan-tmp`.
- Penjadwal sesuai 02 §8 (Asia/Jakarta, `withoutOverlapping`, `onOneServer`).

## [0.7.0] - 2026-10-07

### Ditambahkan
- Namespace slug unit opsional (BR-22): prefiks unit disinkronkan ke slug terlarang, hanya anggota unit yang boleh memakainya dan langsung aktif; perintah `alias:sinkron-prefiks`.
- Pindai ulang tautan aktif saat slug terlarang/aturan domain baru ditambah (laporan sistem untuk moderator, tanpa blokir otomatis).
- Tautan berkata sandi (BR-35) dengan halaman antara tanpa sesi, token HMAC terikat kode, dan batas 5 percobaan/menit per IP+kode.
- Impor massal CSV (`TautanImporter`) dengan validasi identik `BuatTautan`, tabel impor ber-UUID, berkas dihapus setelah selesai.

## [0.6.0] - 2026-10-07

### Ditambahkan
- Laporan penyalahgunaan publik `/lapor` (honeypot, 5/jam/IP, ip_hash bergaram) dan blokir otomatis BR-37 (≥ ambang laporan dari ip_hash berbeda dalam 24 jam).
- Panel moderasi (tinjau, blokir tautan, tolak, tandai ditindaklanjuti) dan widget statistik antrean.
- Pemeriksaan kesehatan tujuan yang aman (HEAD/GET, redirect manual maks 5 hop, validasi tiap hop, IP dipaku, tanpa mengunduh badan), perintah `alias:periksa-tujuan`, aksi "Periksa tujuan sekarang".

## [0.5.0] - 2026-10-07

### Ditambahkan
- Pengalihan `/{kode}` 302 (header BR-09, 404/410 Indonesia, pencocokan kode BR-12) dengan grup middleware `pendek` tanpa sesi/cookie, dimuat paling akhir dan dilindungi dari bentrok rute (uji termasuk rute ter-cache).
- Pratinjau `/{kode}+` (QR inline, tanpa mencatat), beranda publik, halaman `/privasi`.
- Pencatatan kunjungan anonim (IP dianonimkan + HMAC bergaram harian, host perujuk, device-detector di job), deteksi bot, TrustProxies dari `TRUSTED_PROXIES`.
- Tautan sekali pakai & batas klik via UPDATE bersyarat atomik, bot tidak menghabiskan tautan terbatas, teruskan query.

## [0.4.0] - 2026-10-07

### Ditambahkan
- Skema `tautan_pendek` (+ riwayat status & kepemilikan), enum, model, scope `terlihatOleh` dan `TautanPendekPolicy` (BR-19/20).
- Pembangkit kode acak base62, validasi slug kustom, slug terlarang (seeder + resource), deteksi bentrok segmen rute (BR-01–04, BR-36).
- Validasi URL tujuan: normalisasi/punycode, anti-SSRF (IP privat, DNS, format IP tidak standar), aturan domain, mode daftar putih, kata kunci judi (BR-05–08); tabel `pengaturan`.
- Resource tautan (tab, filter, kuota, laju, slug menunggu persetujuan), QR on-the-fly berisi URL pendek.
- Transisi status hanya lewat Action (setujui/tolak/nonaktifkan/aktifkan/blokir/buka blokir/hapus/ajukan ulang) dengan riwayat; antrean persetujuan.
- Transfer kepemilikan sesuai BR-21 dan pemindahan massal (lock + per 200) yang tersambung ke penonaktifan akun.

### Catatan
- MySQL dikecualikan: `ascii_bin` memakai perbandingan biner bawaan SQLite; CHECK constraint diganti trigger SQLite.

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
