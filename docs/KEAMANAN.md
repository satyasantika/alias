# Keamanan Alias FKIP

Hasil F10.1 (checklist 05-UJI-PENERIMAAN §3). Setiap kontrol punya uji otomatis di `tests/Feature/Keamanan`, `tests/Arch`, atau fase asalnya.

| No | Kontrol | Bukti |
|---|---|---|
| K-01 | Registrasi tertutup (BR-31) | `Auth/PanelLoginTest`, `Akses/PermintaanAksesTest` |
| K-02 | MFA wajib super-admin & admin-alias (BR-34) | `Auth/PanelLoginTest` (redirect ke profil), `WajibMfaAdmin` |
| K-03 | Throttle & penguncian login; log login | `Auth/KunciAkunTest`, `PangkasLogLogin` |
| K-04 | Otorisasi per record & matriks PRD §4.3 | `Keamanan/MatriksAksesTest` (≈ 120 sel halaman + izin aksi), `Tautan/OtorisasiTautanTest` |
| K-05 | IDOR: UUID di URL; tautan orang lain 403/404 | `Keamanan/MatriksAksesTest` |
| K-06 | Validasi tujuan BR-05–BR-08 | `Tautan/UrlTujuanTest`, `Keamanan/SsrfTest` |
| K-07 | Cek kesehatan tanpa SSRF (redirect manual, IP dipaku, tanpa unduh badan) | `Tautan/CekTujuanTest`, `Keamanan/SsrfTest` |
| K-08 | Header bebas CR/LF; tanpa open redirect | `Keamanan/OpenRedirectTest` |
| K-09 | Rute catch-all tidak menelan rute sistem, juga pada rute ter-cache | `Rute/BentrokRuteTest` |
| K-10 | Pengalihan tanpa sesi/cookie, noindex, no-store | `Keamanan/HeaderTest`, `Pengalihan/AlihkanTautanTest` |
| K-11 | IP utuh tidak tersimpan; garam harian hanya di cache | `Pengalihan/KunjunganTest` |
| K-12 | Rate limit BR-24 | `Keamanan/RateLimitTest` + uji tiap rute |
| K-13 | XSS ter-escape (panel & publik) | `Keamanan/XssTest` |
| K-14 | Sekali pakai/batas klik atomik | `Pengalihan/KonsumsiKlikTest` |
| K-15 | Tanpa unggahan selain CSV impor; QR/ekspor tidak permanen | `Arch/TanpaUnggahTest`, `Analitik/EksporTest` |
| K-16 | `composer audit` / `npm audit` | lihat di bawah |
| K-17 | `.env`/rahasia tidak di repo; `APP_DEBUG=false` produksi | `.gitignore`, `.env.production.example` |

## Header keamanan

- Pengalihan, pratinjau, galat, halaman kata sandi: `X-Robots-Tag: noindex, nofollow`, `Cache-Control: no-store`, `X-Frame-Options: DENY`, CSP ketat tanpa JavaScript, HSTS bila HTTPS, `Referrer-Policy: unsafe-url` hanya pada 302 (keputusan pemilik produk, PRD BR-09 ¹).
- Beranda, `/privasi`, `/minta-akses`, `/lapor`: `X-Frame-Options: DENY`, CSP dasar, HSTS bila HTTPS.
- Panel Filament memakai header bawaannya sendiri.

## Hasil audit dependensi (2026-10-07)

- `composer audit`: tidak ada advisori. (Filament ditingkatkan ke 5.9 pada F1.3 yang menutup dua advisori MFA pada 4.11.7.)
- `npm audit`: 0 kerentanan (`package-lock.json` dibuat dan dikomit agar CI `npm ci` berjalan).

## Keputusan & batasan yang dicatat

- Login Google tidak berlaku untuk peran admin (agar tantangan MFA tidak terlewati) dan tidak pernah membuat akun.
- Tautan berkata sandi memakai token HMAC terikat kode (jendela 10 menit) sebagai pengganti sesi+CSRF, sehingga halaman pengalihan tetap tanpa cookie.
- Pengelompokan "pengunjung unik" per hari (garam harian membuat hash tak dapat dikaitkan lintas hari) — disengaja demi privasi.
- Kegagalan MFA pada tantangan login belum menghasilkan peristiwa `mfa_gagal` karena Filament tidak mengeluarkan event untuk itu (dibatasi throttle bawaan Filament 5/menit).
