@extends('layouts.publik')
@section('judul', 'Beranda')
@section('kelas-main', 'lebar')
@section('isi')
    <div class="hero">
        <p class="label">Layanan resmi FKIP Universitas Siliwangi</p>
        <h1>Satu tautan pendek, selalu bisa dilacak dan dipercaya.</h1>
        <p class="lead">Alias FKIP memendekkan tautan panjang milik dosen, tenaga kependidikan, dan unit kerja FKIP. Tautan tetap berlaku saat pegawai berpindah tugas, dan setiap klik dicatat secara anonim untuk statistik penggunaan.</p>
        <p>
            <a class="tombol" href="{{ url('/panel/login') }}">Masuk</a>
            <a class="tombol tombol-garis" href="{{ url('/minta-akses') }}">Minta akses</a>
        </p>
    </div>

    <div class="fitur">
        <div class="kad">
            <h3>Dimiliki unit atau pegawai</h3>
            <p>Tautan melekat pada prodi, unit kerja, atau akun pegawai — bukan sekadar daftar tautan lepas.</p>
        </div>
        <div class="kad">
            <h3>Tetap berlaku saat pindah tugas</h3>
            <p>Saat pemilik berpindah atau purnatugas, tautan dapat dialihkan tanpa mengubah kode maupun statistik.</p>
        </div>
        <div class="kad">
            <h3>Statistik klik anonim</h3>
            <p>Setiap klik tercatat tanpa menyimpan IP utuh pengunjung, sesuai pemberitahuan privasi.</p>
        </div>
    </div>

    <div class="panduan">
        <h2>Panduan per peran</h2>
        <p class="bantu">Panduan bergambar langkah demi langkah:</p>
        <ul>
            <li><a href="{{ url('/panduan/pengguna.html') }}">Pengguna</a> (dosen &amp; tendik)</li>
            <li><a href="{{ url('/panduan/pengelola.html') }}">Pengelola unit</a></li>
            <li><a href="{{ url('/panduan/admin.html') }}">Admin Alias</a></li>
            <li><a href="{{ url('/panduan/pemantau.html') }}">Pemantau</a> (pimpinan)</li>
        </ul>
        <p class="bantu">Menerima tautan mencurigakan? <a href="{{ url('/lapor') }}">Laporkan</a>. Tambahkan <strong>+</strong> di akhir tautan pendek untuk melihat tujuannya sebelum membuka.</p>
    </div>
@endsection
