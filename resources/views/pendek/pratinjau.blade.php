@extends('layouts.publik')
@section('judul', 'Pratinjau tautan')
@section('isi')
    <h1>{{ $tautan->judul }}</h1>

    @if ($diblokir)
        <p class="galat" role="alert">Tautan ini dinonaktifkan karena melanggar ketentuan. Tujuannya tidak ditampilkan.</p>
    @elseif ($tautan->trashed())
        <p class="galat" role="alert">Tautan ini sudah dihapus oleh pemiliknya.</p>
    @endif

    <dl>
        <dt class="bantu">Tautan pendek</dt>
        <dd><strong>{{ $tautan->url_pendek }}</strong></dd>

        @if ($tampilkanTujuan)
            <dt class="bantu">Mengarah ke</dt>
            <dd>
                <strong>{{ $tautan->host_tujuan }}</strong><br>
                <span style="word-break: break-all;">{{ $tautan->url_tujuan }}</span>
            </dd>
        @endif

        <dt class="bantu">Pemilik</dt>
        <dd>{{ $pemilik }}</dd>

        <dt class="bantu">Dibuat</dt>
        <dd>{{ $tautan->created_at->translatedFormat('d F Y') }}</dd>
    </dl>

    <p><img src="{{ $qr }}" width="220" height="220" alt="Kode QR untuk {{ $tautan->url_pendek }}"></p>

    <p>
        @if ($dapatDilanjutkan)
            <a class="tombol" href="{{ url('/'.$tautan->kode) }}" rel="nofollow">Lanjutkan</a>
        @endif
        <a href="{{ url('/lapor') }}?kode={{ urlencode($tautan->kode) }}">Laporkan tautan ini</a>
    </p>
@endsection
