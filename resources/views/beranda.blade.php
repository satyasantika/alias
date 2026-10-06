@extends('layouts.publik')
@section('judul', 'Beranda')
@section('isi')
    <h1>Alias FKIP</h1>
    <p>Layanan tautan pendek resmi Fakultas Keguruan dan Ilmu Pendidikan Universitas Siliwangi. Tautan dimiliki oleh unit atau pegawai, tetap berlaku saat pegawai berpindah tugas, dan statistik kliknya dicatat secara anonim.</p>
    <p>
        <a class="tombol" href="{{ url('/panel/login') }}">Masuk</a>
        <a href="{{ url('/minta-akses') }}">Minta akses</a>
    </p>
    <p class="bantu">Menerima tautan mencurigakan? <a href="{{ url('/lapor') }}">Laporkan</a>. Tambahkan <strong>+</strong> di akhir tautan pendek untuk melihat tujuannya sebelum membuka.</p>
@endsection
