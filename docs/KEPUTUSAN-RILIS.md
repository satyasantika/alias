# Keputusan Rilis v1.0.0

Status per butir "perlu verifikasi" yang memengaruhi produksi. **Diputuskan** = ada keputusan teknis yang terdokumentasi dan teruji. **Default** = aplikasi memakai nilai bawaan yang aman sampai pemilik memutuskan. **Menunggu pemilik** = hanya pemilik produk/UPT TIK yang dapat memutuskan; belum diputuskan pada rilis ini.

## Keputusan teknis (selesai)

| Butir | Keputusan | Dasar |
|---|---|---|
| MySQL | Dikecualikan; SQLite pada volume; migrasi tetap kompatibel MySQL (`ascii_bin`, CHECK) | Arahan pemilik; `docs/DEPLOY.md` |
| Versi Filament | **Filament 5.9 / Livewire 4** (tanpa blocker; menutup 2 advisori MFA pada 4.11.7) | F1.3, CHANGELOG 0.1.0 |
| Shield | Tidak dipakai; Policy & seeder eksplisit | CHANGELOG 0.2.0 |
| Cache lookup tautan | **Tetap nonaktif** (`ALIAS_CACHE_LOOKUP=false`): p95 27–45 ms pada 50–100 rps tanpa cache; bottleneck ada di jumlah worker PHP-FPM | `docs/BEBAN.md` |
| `Referrer-Policy` pengalihan | `unsafe-url` hanya pada respons 302 (BR-09 ¹) — dapat diganti `strict-origin-when-cross-origin` bila pemilik menilai berisiko | `docs/KEAMANAN.md` |
| Login Google | Opsional, nonaktif secara bawaan; tidak membuat akun; tidak untuk peran admin | F2.5 |
| Halaman kata sandi tautan | Token HMAC terikat kode (tanpa sesi/cookie) | F7.3 |
| Retensi | Kunjungan manusia 12 bulan, bot 30 hari, log login 90 hari, log aktivitas 24 bulan (`activitylog:clean` 730 hari) — dapat diubah di Pengaturan sistem | F8.2, F9.4 |

## Menunggu pemilik produk / UPT TIK (belum diputuskan)

| No | Butir | Default yang berlaku | Penanggung jawab | Berkas terkait |
|---|---|---|---|---|
| G-01 | Domain pendek & domain panel (mis. `go.fkip.unsil.ac.id`, `alias.fkip.unsil.ac.id`), DNS, TLS | Mode satu domain (`ALIAS_DOMAIN_PENDEK` kosong) | TI fakultas / UPT TIK | `.env.production.example` |
| G-02 | Reverse proxy & `TRUSTED_PROXIES`/`NGINX_REAL_IP_FROM` | Kosong (IP proxy akan tercatat sebagai IP klien bila ada proxy) | TI fakultas | `docs/DEPLOY.md` §1 |
| G-03 | Rentang IP NAT kampus & batas laju pengalihan | 300 permintaan/menit/IP (`ALIAS_LAJU_PENGALIHAN`) | TI fakultas | `docs/DEPLOY.md` §1 |
| G-04 | Domain surel pengurus ormawa (`student.unsil.ac.id`?) | `unsil.ac.id` dan `staff.unsil.ac.id` (`ALIAS_DOMAIN_SUREL`, dipisah koma bila lebih dari satu) | Pemilik produk | BR-32 |
| G-05 | Persetujuan teks `/privasi`; kebijakan arsip log aktivitas | Teks ringkas di Pengaturan sistem; retensi 24 bulan; **pasal UU PDP/ITE/PP 71 tidak dikutip** | Pemilik produk | `docs/PANDUAN-ADMIN.md` §6 |
| G-06 | Tinjauan daftar slug kelembagaan & kata tidak pantas | Daftar awal di seeder (`SlugTerlarangSeeder`, `kata-tidak-pantas.txt`) | Admin alias (humas) | 03 §6.3 |
| G-07 | Daftar unit/prodi FKIP resmi + pengelola | Hanya FKIP & PMAT di `unit.csv` | Admin alias | `database/seeders/data/unit.csv` |
| G-08 | ≥ 2 akun super-admin ber-MFA | — | TI fakultas | `docs/DEPLOY.md` §2 |
| G-09 | Backup harian & uji pulih | Prosedur SQLite `VACUUM INTO` terdokumentasi | TI fakultas | `docs/DEPLOY.md` §5 |
| G-11 | UAT bersama pengguna nyata (05 §2) | **Belum dilaksanakan.** `alias:siapkan-uat` + skenario tertaut ke uji otomatis di bawah | Pemilik produk | `docs/PANDUAN-ADMIN.md` |
| G-12 | Sosialisasi & pemindahan tautan bit.ly/s.id unit (impor CSV) | — | Admin alias | `docs/PANDUAN-PENGGUNA.md` |
| — | Warna identitas Unsil, logo pada QR/pratinjau | Biru (`Color::Blue`), tanpa logo | Pemilik produk | `AliasPanelProvider` |
| — | Domain pemendek resmi universitas (dikecualikan dari daftar blokir bila ada) | Tidak ada pengecualian | UPT TIK | `AturanDomainSeeder` |
| — | Plugin Filament pihak ketiga saat upgrade berikutnya | Impersonate 5.6 kompatibel | Pengembang | — |

## Keterlacakan skenario UAT → uji otomatis

Skenario manual di 05-UJI-PENERIMAAN §2 tetap harus dijalankan oleh pengguna nyata (G-11). Perilakunya sudah dijaga uji otomatis:

| Skenario | Uji otomatis |
|---|---|
| L-01–L-03 | `Auth/PanelLoginTest`, `Akses/PermintaanAksesTest` |
| L-04 MFA admin | `Auth/PanelLoginTest` |
| L-05, L-06 | `Auth/KunciAkunTest` |
| L-07 | `Auth/PanelLoginTest` (reset kata sandi, sesi lain berakhir) |
| L-08 | `Auth/AuditDanGoogleTest` |
| P-01–P-04, P-07 | `Tautan/BuatTautanTest`, `Tautan/UrlTujuanTest`, `Tautan/KodeDanSlugTest` |
| P-05 | `Tautan/BuatTautanTest` (QR berisi URL pendek) |
| P-06 | `Pengalihan/KunjunganTest`, `Analitik/StatistikTautanTest` |
| P-08 | `Keamanan/MatriksAksesTest` (IDOR) |
| P-09 | `Tautan/TransferKepemilikanTest` |
| P-10, P-11 | `Pengalihan/KonsumsiKlikTest` |
| U-01–U-05 | `Unit/UnitTest`, `Tautan/NamespaceUnitTest`, `Analitik/DasborPerPeranTest`, `Analitik/EksporTest` |
| A-01–A-07 | `Tautan/TransisiStatusTest`, `Moderasi/*`, `Pengguna/PenggunaTest`, `Tautan/ImporTautanTest` |
| M-01–M-03 | `Analitik/DasborPerPeranTest` |
| S-01–S-03 | `Pengaturan/PengaturanSistemTest`, `Peran/MatriksPermissionTest`, `Pengguna/PenggunaTest` |
| T-01–T-05 | `Pengalihan/*`, `Moderasi/LaporanPublikTest`, `Keamanan/HeaderTest` |
| T-06 (ponsel 360 px) | **Manual** — belum diuji otomatis |
