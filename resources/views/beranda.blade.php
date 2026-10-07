@extends('layouts.publik')
@section('judul', 'Beranda')
@section('isi')
    <h1>Alias FKIP</h1>
    <p>Layanan tautan pendek resmi Fakultas Keguruan dan Ilmu Pendidikan Universitas Siliwangi. Tautan dimiliki oleh unit atau pegawai, tetap berlaku saat pegawai berpindah tugas, dan statistik kliknya dicatat secara anonim.</p>
    <p>
        <a class="tombol" href="{{ url('/panel/login') }}">Masuk</a>
        <a href="{{ url('/minta-akses') }}">Minta akses</a>
    </p>
    <h2 style="font-size:1.125rem">Panduan per peran</h2>
    <p class="bantu">Panduan bergambar langkah demi langkah:</p>
    <ul>
        <li><a href="{{ url('/panduan/pengguna.html') }}">Pengguna</a> (dosen &amp; tendik)</li>
        <li><a href="{{ url('/panduan/pengelola.html') }}">Pengelola unit</a></li>
        <li><a href="{{ url('/panduan/admin.html') }}">Admin Alias</a></li>
        <li><a href="{{ url('/panduan/pemantau.html') }}">Pemantau</a> (pimpinan)</li>
    </ul>
    <p class="bantu">Menerima tautan mencurigakan? <a href="{{ url('/lapor') }}">Laporkan</a>. Tambahkan <strong>+</strong> di akhir tautan pendek untuk melihat tujuannya sebelum membuka.</p>
@endsection
