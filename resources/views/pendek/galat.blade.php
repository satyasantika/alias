@extends('layouts.publik')
@section('judul', $judul)
@section('isi')
    <p class="kode-galat">{{ $status }}</p>
    <h1>{{ $judul }}</h1>
    <p>{{ $pesan }}</p>
    <p class="bantu">Kode tautan: <strong>{{ $kode }}</strong></p>
    <p>
        <a class="tombol tombol-garis" href="{{ url()->previous('/') }}">Kembali</a>
        <a class="tombol" href="{{ url('/') }}">Ke beranda</a>
    </p>
    <p><a href="{{ url('/lapor') }}?kode={{ urlencode($kode) }}">Laporkan tautan ini</a></p>
@endsection
