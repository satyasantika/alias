@extends('layouts.publik')
@section('judul', 'Akses ditolak')
@section('isi')
    <p class="kode-galat">403</p>
    <h1>Akses ditolak</h1>
    <p>Anda tidak memiliki izin untuk membuka halaman ini. Bila merasa ini keliru, hubungi admin Alias FKIP.</p>
    <p>
        <a class="tombol tombol-garis" href="{{ url()->previous('/') }}">Kembali</a>
        <a class="tombol" href="{{ url('/') }}">Ke beranda</a>
    </p>
@endsection
