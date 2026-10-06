# Uji Beban Pengalihan (F10.2)

Tujuan: membuktikan p95 < 100 ms pada ≥ 50 rps **tanpa** cache lookup (02-ARSITEKTUR §4) sehingga `ALIAS_CACHE_LOOKUP` tetap `false`.

## Cara uji

1. Basis data khusus (bukan DB pengembangan): `DB_DATABASE=database/perf.sqlite php artisan migrate --force`, lalu
   `DB_DATABASE=database/perf.sqlite APP_ENV=local php artisan db:seed --class=PerfSeeder` → **50.000 tautan + 1.000.000 kunjungan** (±47 dtk, ±416 MB).
2. Aplikasi mode produksi (`APP_ENV=production`, `APP_DEBUG=false`, `config:cache`, `route:cache`) di container PHP-FPM 8.5 terpisah + nginx, antrean Redis di DB terpisah (`REDIS_QUEUE_DB=7`, agar Horizon pengembangan tidak ikut memproses), `ALIAS_LAJU_PENGALIHAN` dinaikkan agar pembatas laju per-IP tidak mengganggu satu sumber beban.
3. k6 (`tests/beban/pengalihan.js`): laju tetap (`constant-arrival-rate`), 60 detik, memukul 1.000 kode acak, redirect tidak diikuti, `User-Agent` peramban:
   ```bash
   docker run --rm --network <jaringan> -v <folder-kode>:/data -v $PWD/tests/beban:/skrip \
     -e BASE=http://alias-perf-nginx -e RPS=50 -e DURASI=60s grafana/k6 run /skrip/pengalihan.js
   ```
   Daftar kode: `select kode from tautan_pendek order by random() limit 1000` → `/data/kode.json`.

Setiap permintaan menjalankan alur penuh: satu kueri `kode` + pembentukan IP anonim/HMAC (cache) + `RPUSH` job `CatatKunjungan` ke Redis + 302.

## Hasil (WSL2, container PHP-FPM bawaan: `pm = dynamic`, `max_children = 5`, tanpa penalaan)

| Laju | Run | p50 | p95 | p99 | Galat |
|---|---|---|---|---|---|
| 50 rps | 1 | 18,9 ms | **27,8 ms** | 31,7 ms | 0% |
| 50 rps | 2 | 19,8 ms | **33,9 ms** | 52,1 ms | 0% |
| 50 rps | 3 | 19,2 ms | **30,5 ms** | 39,7 ms | 0% |
| 100 rps | 1 | 25,8 ms | 2,11 s ⚠ | 2,61 s | 0% |
| 100 rps | 2 | 17,8 ms | **36,2 ms** | 146 ms | 0% |
| 100 rps | 3 | 17,8 ms | **44,7 ms** | 221 ms | 0% |

Catatan pengamatan:
- **50 rps lulus konsisten** (p95 27–34 ms, p99 ≤ 52 ms, 0 galat) — target p95 < 100 ms tercapai dengan margin ±3×.
- Pada 100 rps, 2 dari 3 run lulus (p95 36–45 ms). Satu run mengalami stagnasi (p95 2,1 s). Kuat dugaan penyebabnya kolam PHP-FPM bawaan (hanya 5 *worker*) yang jenuh sesaat di WSL2; pada 200 rps kolam itu jenuh total (p95 > 10 s). Ini batas konfigurasi proses, bukan basis data.
- Request pertama setelah container dihidupkan ±1,2 s (pemanasan OPcache); tidak berulang.
- Kueri pengalihan: `EXPLAIN QUERY PLAN` → `SEARCH tautan_pendek USING INDEX tautan_pendek_kode_unique (kode=?)`; waktu ±**0,005 ms** per kueri pada 50.000 tautan (SQLite). Dasbor memakai tabel rekap (`rekap_kunjungan_*`), bukan agregasi tabel kunjungan 1 juta baris.
- Antrean: 26.859 job `CatatKunjungan` menumpuk selama uji karena tidak ada Horizon pada lingkungan uji; `RPUSH` terbukti tidak menambah latensi yang terukur.

## Keputusan

**`ALIAS_CACHE_LOOKUP` tetap `false`.** Waktu kueri mikrodetik dan bottleneck terukur ada di jumlah proses PHP-FPM, bukan di pencarian kode; cache lookup menambah masalah invalidasi (blokir/ubah tujuan harus seketika) tanpa manfaat terukur.

Rekomendasi untuk produksi (diterapkan di F10.3): `pm = static`, `pm.max_children` ≥ 20 pada `alias-php`, OPcache `validate_timestamps=0`, dan uji ulang pada staging dengan MySQL 8.4 sungguhan (uji ini memakai SQLite karena MySQL dikecualikan dari lingkungan pengembangan). Bila target 100 rps dibutuhkan, ulangi uji setelah penalaan itu; **jangan** mengaktifkan cache lookup tanpa persetujuan pemilik.

## Temuan sampingan

Container `alias-horizon` di compose pengembangan memakai perintah menunggu `php artisan list | grep horizon:work` yang tidak pernah terpenuhi, sehingga Horizon tidak pernah berjalan. Diperbaiki menjadi `php artisan horizon` (Horizon kini `running`).
