@extends('layouts.publik')
@section('judul', 'Terlalu banyak permintaan')
@section('isi')
    <p class="kode-galat">429</p>
    <h1>Terlalu banyak permintaan</h1>
    <p>Anda mengirim permintaan terlalu sering. Tunggu beberapa saat lalu coba lagi.</p>
    <p>
        <a class="tombol tombol-garis" href="{{ url()->previous('/') }}">Kembali</a>
        <a class="tombol" href="{{ url('/') }}">Ke beranda</a>
    </p>
@endsection
