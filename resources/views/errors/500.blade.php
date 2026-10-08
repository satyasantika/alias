@extends('layouts.publik')
@section('judul', 'Terjadi kesalahan pada server')
@section('isi')
    <p class="kode-galat">500</p>
    <h1>Terjadi kesalahan pada server</h1>
    <p>Mohon maaf, terjadi kesalahan di sisi kami. Tim Alias FKIP sudah diberi tahu. Silakan coba lagi beberapa saat lagi.</p>
    <p>
        <a class="tombol tombol-garis" href="{{ url()->previous('/') }}">Kembali</a>
        <a class="tombol" href="{{ url('/') }}">Ke beranda</a>
    </p>
@endsection
