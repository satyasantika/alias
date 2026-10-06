@extends('layouts.publik')
@section('judul', 'Tautan berkata sandi')
@section('isi')
    <h1>{{ $tautan->judul }}</h1>
    <p>Tautan ini dilindungi kata sandi. Masukkan kata sandi yang diberikan pemilik tautan.</p>

    <form method="POST" action="{{ url('/'.$kode) }}" novalidate>
        <input type="hidden" name="token" value="{{ $token }}">
        <label for="kata_sandi">Kata sandi</label>
        <input id="kata_sandi" type="password" name="kata_sandi" required autocomplete="off" autofocus>
        @if ($galat)<div class="galat" role="alert">{{ $galat }}</div>@endif
        <button type="submit">Buka tautan</button>
    </form>

    <p class="bantu"><a href="{{ url('/lapor') }}?kode={{ urlencode($kode) }}">Laporkan tautan ini</a></p>
@endsection
