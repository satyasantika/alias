@extends('layouts.publik')
@section('judul', 'Sesi telah berakhir')
@section('isi')
    <p class="kode-galat">419</p>
    <h1>Sesi telah berakhir</h1>
    <p>Halaman ini terbuka terlalu lama. Muat ulang halaman sebelumnya lalu kirim formulir kembali.</p>
    <p>
        <a class="tombol tombol-garis" href="{{ url()->previous('/') }}">Kembali</a>
        <a class="tombol" href="{{ url('/') }}">Ke beranda</a>
    </p>
@endsection
