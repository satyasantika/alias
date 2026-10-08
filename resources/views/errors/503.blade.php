@extends('layouts.publik')
@section('judul', 'Sedang pemeliharaan')
@section('isi')
    <p class="kode-galat">503</p>
    <h1>Layanan sedang pemeliharaan</h1>
    <p>Alias FKIP sedang dalam pemeliharaan terjadwal. Silakan coba lagi beberapa saat lagi.</p>
    <p>
        <a class="tombol" href="{{ url('/') }}">Ke beranda</a>
    </p>
@endsection
