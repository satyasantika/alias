# Catatan untuk PR berikutnya

Butir yang sengaja ditunda; kerjakan sebagai PR terpisah.

1. **Tangkapan layar Horizon** pada panduan Super Admin (instance demo belum memakai Redis; saat ini hanya teks). Jalankan demo dengan Redis + `alias-horizon`, tambahkan entri di `tools/panduan/tangkap.js` dan `isi.php`, lalu `php tools/panduan/bangun.php <folder-gambar>`.
2. **Bangun ulang panduan bila tampilan panel berubah** (`tools/panduan/tangkap.js` → `bangun.php`).
3. **Verifikasi di server situs dukungan** `https://supportfkip.unsil.ac.id/alias`: login panel, Livewire, QR, satu `/alias/<kode>`, dan panduan `/alias/panduan/` (baru teruji lewat test & simulasi).
4. **Uji layar 360 px** halaman publik (skenario T-06) dan UAT dengan pengguna nyata (lihat `docs/KEPUTUSAN-RILIS.md`).
5. **Uji beban ulang** dengan MySQL/penalaan PHP-FPM produksi (lihat `docs/BEBAN.md`).
