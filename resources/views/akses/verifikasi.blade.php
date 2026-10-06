@extends('layouts.publik')
@section('judul', 'Verifikasi surel')
@section('isi')
    @if ($berhasil)
        <h1>Surel terverifikasi</h1>
        <p class="info" role="status">Terima kasih. Permintaan akses Anda sedang ditinjau admin. Anda akan menerima surel setelah ada keputusan.</p>
    @else
        <h1>Tautan tidak berlaku</h1>
        <p>Tautan verifikasi ini sudah tidak berlaku. Silakan <a href="{{ route('akses.formulir') }}">ajukan permintaan baru</a>.</p>
    @endif
@endsection
