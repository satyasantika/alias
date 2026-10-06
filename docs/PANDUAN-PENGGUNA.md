# Panduan Pengguna Alias FKIP

Alias FKIP memendekkan tautan panjang menjadi alamat resmi fakultas (mis. `go.fkip.unsil.ac.id/seminar-pmat-2026`) dan mencatat klik secara anonim.

## 1. Masuk
1. Buka `…/panel/login`, masukkan surel **@unsil.ac.id** dan kata sandi.
2. Belum punya akun? Pilih **Minta akses**, isi formulir, lalu klik tautan verifikasi yang dikirim ke surel Anda (berlaku 24 jam). Admin akan menyetujui; Anda menerima surel untuk mengatur kata sandi.
3. Lupa kata sandi? Klik **Lupa kata sandi?** di halaman masuk.
4. Akun terkunci 15 menit bila salah kata sandi 10 kali dalam 30 menit.

> 📷 *[Tangkapan layar: halaman masuk]*

## 2. Membuat tautan pendek
1. Menu **Tautan → Buat tautan**.
2. Tempel **URL tujuan** (harus `http://` atau `https://`; tautan pemendek lain seperti bit.ly tidak diterima) dan isi **Judul**.
3. Simpan. Kode acak 7 karakter dibuat otomatis. Klik kode di daftar untuk menyalin URL pendek.

Tautan pribadi dibatasi kuota (bawaan 100). Anda juga dapat memilih **Unit** sebagai pemilik bila Anda anggotanya (disarankan untuk tautan resmi seperti pendaftaran dan akreditasi).

> 📷 *[Tangkapan layar: formulir buat tautan]*

## 3. Slug kustom
Aktifkan **Pakai slug kustom** lalu isi mis. `seminar-pmat-2026` (3–50 karakter: huruf kecil, angka, strip tunggal). Ditolak bila: format salah, kata yang dicadangkan sistem/kelembagaan (mis. `panel`, `rektor`), mengandung kata tidak pantas, atau sudah dipakai (termasuk tautan yang sudah dihapus).
- Slug kustom dari **pengguna biasa** berstatus *Menunggu persetujuan* sampai admin menyetujui (tautan belum bisa dibuka). Bila ditolak, ubah slug lalu ajukan ulang.
- Pengelola unit dan admin: slug langsung aktif.

## 4. Pengaturan lanjutan
- **Jadwal**: aktif mulai / aktif sampai (sebelum mulai tampil "belum aktif"; setelah selesai "kedaluwarsa").
- **Batas klik** dan **Sekali pakai** (mis. undangan pribadi). Pratinjau WhatsApp/Telegram tidak menghabiskan tautan.
- **Teruskan parameter query**: `?utm_source=wa` ikut ke tujuan tanpa menimpa parameter yang sudah ada.
- **Kata sandi tautan**: pengunjung harus memasukkan kata sandi sebelum dialihkan.

## 5. QR
Di daftar tautan klik ikon **QR** (PNG) — kode berisi *URL pendek*, bukan tujuan, dan dibuat saat diminta (tidak disimpan). Anda bebas mencetaknya di poster.

## 6. Statistik
Klik **Statistik** pada tautan: grafik klik 30/90 hari, perangkat, dan perujuk teratas. Pengunjung unik dihitung **per hari** (demi privasi, penanda IP berganti tiap hari). IP utuh tidak pernah tampil; tabel *Kunjungan* hanya menampilkan IP yang sudah dianonimkan (`103.21.44.0`). Bot tidak dihitung sebagai klik.

## 7. Pratinjau & melaporkan
Siapa pun dapat menambahkan `+` di akhir tautan (`/kode+`) untuk melihat tujuan sebelum membuka. Tautan mencurigakan dapat dilaporkan di `/lapor`.

## 8. Memindahkan ke unit
Aksi **Pindahkan kepemilikan** pada tautan pribadi → pilih unit tempat Anda anggota. Kode, tujuan, dan statistik tidak berubah; riwayat tercatat. Bila Anda berpindah tugas, admin dapat mengalihkan semua tautan Anda sekaligus.

## 9. Status tautan
| Status | Arti |
|---|---|
| Aktif | Dapat dibuka |
| Menunggu persetujuan | Slug kustom menunggu admin (belum bisa dibuka) |
| Dinonaktifkan | Anda menonaktifkan; dapat diaktifkan lagi |
| Diblokir | Dihentikan moderator karena pelanggaran; hanya admin yang dapat membuka |
| Ditolak | Slug ditolak; ubah slug lalu ajukan ulang atau hapus |

Menghapus tautan yang pernah aktif tidak membebaskan kodenya (agar QR/poster lama tidak mengarah ke tujuan lain).

## 10. Notifikasi
Lonceng di panel dan surel memberi tahu: slug disetujui/ditolak, tautan diblokir/dibuka, tujuan bermasalah, tautan akan kedaluwarsa (7 hari), dan tautan dipindahkan.
