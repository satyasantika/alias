# Panduan Admin Alias FKIP

Untuk **admin Alias** dan **super admin**. Wajib mengaktifkan MFA (aplikasi autentikator) saat masuk pertama.

## 1. Pengguna & unit
- **Pengguna → Pengguna → Buat pengguna**: nama, surel @unsil.ac.id, peran, unit. Pengguna menerima surel *atur kata sandi*. Peran `super-admin`/`admin-alias` hanya dapat diberikan **super admin**.
- **Nonaktifkan**: pilih tujuan pengalihan (unit/pengguna) agar semua tautan pribadinya berpindah; tautan tetap berfungsi. Akun tidak dihapus bila memiliki tautan. Super admin aktif terakhir tidak dapat dinonaktifkan.
- **Unit**: kode, jenis, induk, kuota, prefiks slug. Tambah/keluarkan anggota dan pengelola (pengelola terakhir tidak dapat dikeluarkan).
- **Permintaan akses**: tinjau yang sudah terverifikasi surelnya; **Setujui** (pilih peran & unit) atau **Tolak** (alasan wajib).

## 2. Persetujuan slug
**Tautan → Antrean persetujuan**: setujui, atau tolak dengan alasan ≥ 10 karakter. Tanpa keputusan dalam 14 hari, slug ditolak otomatis.

## 3. Moderasi
- **Moderasi → Laporan penyalahgunaan**: *Tinjau*, *Blokir tautan* (alasan wajib; semua laporan terkait ikut ditindaklanjuti), *Tolak laporan* (alasan wajib), atau *Tandai ditindaklanjuti*. Ekspor rekap XLSX tersedia.
- **Blokir otomatis**: ≥ 3 laporan phishing/malware/judi dari jaringan berbeda dalam 24 jam memblokir tautan dan memberi tahu pemilik (ambang diatur di Pengaturan sistem; 0 = nonaktif). Tinjau dan buka blokir bila keliru.
- **Slug terlarang** dan **Aturan domain**: tambah entri; tautan aktif yang melanggar aturan baru muncul di antrean laporan (tidak diblokir otomatis). Pola domain `*.contoh.com` mencakup domain dan semua subdomainnya. Pemendek tautan pihak ketiga sudah diblokir.
- **Mode domain** (Pengaturan): *bebas* atau *daftar putih* (hanya domain yang diizinkan).

## 4. Tautan
Admin melihat semua tautan, dapat menonaktifkan, memblokir, memindahkan kepemilikan (juga massal), menyetel redirect 301 / mematikan pencatatan, dan **mengimpor CSV** (kolom: `judul,url_tujuan,slug,jenis_kepemilikan,email_pemilik,kode_unit,aktif_sampai`; contoh tersedia di tombol Impor). Baris gagal dapat diunduh dengan alasan; berkas CSV dihapus setelah selesai.

## 5. Statistik & ekspor
Dasbor sesuai peran, **Statistik fakultas** (filter bulan), dan ekspor rekap tautan/unit/kunjungan (berkas hanya di penyimpanan sementara 24 jam; tidak memuat `ip_hash` atau kata sandi).

## 6. Pengaturan sistem (super admin)
Kuota bawaan, persetujuan slug, mode domain, retensi (kunjungan 12 bulan, bot 30 hari, log login 90 hari), ambang blokir otomatis, pengingat kedaluwarsa, dan teks halaman privasi — berlaku langsung tanpa deploy.

## 7. Log
- **Log aktivitas** (admin & super admin): siapa mengubah apa (nilai lama → baru), tanpa kata sandi/rahasia.
- **Log login** (super admin): peristiwa autentikasi dengan IP utuh (khusus keamanan), disimpan 90 hari.

## 8. Horizon & penjadwal
`/horizon` (super admin) memantau antrean `kunjungan`, `notifikasi`, `cek-tujuan`, `ekspor`, `default`. Penjadwal harian: rekap kunjungan 00:20, pangkas kunjungan 01:10, log login 01:30, tolak kedaluwarsa 06:00, pengingat 07:05; mingguan: cek tujuan (Minggu 02:15), laporan tautan yatim (Senin 07:10); per jam: bersihkan berkas sementara. Bila antrean macet, kunjungan tetap dialihkan tetapi statistik tertunda.

## 9. Menanggapi insiden
1. Tautan phishing/judi: **Blokir** dengan alasan; pemilik diberi tahu.
2. Akun diduga disusupi: nonaktifkan akun, alihkan tautan, periksa Log login & Log aktivitas.
3. Lonjakan 429 dari kampus: naikkan `ALIAS_LAJU_PENGALIHAN` (NAT).
4. Kunjungan tidak tercatat: periksa Horizon (`kunjungan`), Redis, dan `/api/health`.
