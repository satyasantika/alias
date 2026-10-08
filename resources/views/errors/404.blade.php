@extends('layouts.publik')
@section('judul', 'Halaman tidak ditemukan')
@section('isi')
    <p class="kode-galat">404</p>
    <h1>Halaman tidak ditemukan</h1>
    <p>Alamat yang Anda tuju tidak ada atau sudah dipindahkan.</p>
    <p>
        <a class="tombol tombol-garis" href="{{ url()->previous('/') }}">Kembali</a>
        <a class="tombol" href="{{ url('/') }}">Ke beranda</a>
    </p>
@endsection
