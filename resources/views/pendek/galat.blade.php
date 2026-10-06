@extends('layouts.publik')
@section('judul', $judul)
@section('isi')
    <h1>{{ $judul }}</h1>
    <p>{{ $pesan }}</p>
    <p class="bantu">Kode: <strong>{{ $kode }}</strong> · Status {{ $status }}</p>
    <p>
        <a class="tombol" href="{{ url('/') }}">Ke beranda</a>
        <a href="{{ url('/lapor') }}?kode={{ urlencode($kode) }}">Laporkan tautan ini</a>
    </p>
@endsection
