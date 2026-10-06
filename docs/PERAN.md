# Peran di Alias FKIP

Alias memakai lima peran. Satu orang boleh memegang lebih dari satu peran.

| Peran | Siapa pemegangnya | Boleh | Tidak boleh |
|---|---|---|---|
| **Super admin** | TI fakultas | Semuanya: pengaturan sistem, Horizon, log login, memberi peran admin | — (wajib MFA) |
| **Admin Alias** | Operator humas/TI yang ditunjuk dekanat | Kelola semua tautan dan pengguna, setujui slug kustom, moderasi, blokir, aturan domain, impor massal, lihat log aktivitas | Memberi/mencabut peran admin, pengaturan sistem, Horizon, log login (wajib MFA) |
| **Pengelola unit** | Admin prodi/jurusan/unit kerja/ormawa | Kelola tautan dan anggota unit yang ia kelola, statistik dan ekspor unit; semua hak Pengguna | Menyetujui slug, memblokir, mengatur 301, mengelola unit lain |
| **Pengguna** | Dosen dan tendik | Buat dan kelola tautan pribadi dalam kuota; sebagai anggota unit, buat tautan atas nama unit; lihat statistik tautannya | Melihat tautan orang lain, slug kustom tanpa persetujuan (bila dipersyaratkan) |
| **Pemantau** | Pimpinan (dekan, wakil dekan, ketua jurusan, gugus mutu) | Melihat statistik agregat dan mengekspornya | Melihat rincian tautan/kunjungan, mengubah apa pun |

## Aturan penting

- Registrasi terbuka **ditutup**. Akun dibuat admin atau lewat permintaan akses yang surelnya terverifikasi.
- Surel wajib berakhiran `@unsil.ac.id` (persis, bukan sekadar mengandung).
- Peran **super admin** dan **admin Alias** hanya dapat diberikan oleh super admin.
- Admin wajib mengaktifkan MFA; setelah itu baru dapat membuka halaman panel lain. Peran admin tidak dapat masuk lewat Google (agar tantangan MFA tidak terlewati).
- Akun dikunci 15 menit setelah 10 kali gagal masuk dalam 30 menit; pemilik menerima surel pemberitahuan.
- Cakupan data (milik sendiri, unit, semua) ditentukan Policy per record, bukan hanya oleh peran.
