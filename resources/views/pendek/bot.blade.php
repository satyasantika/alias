@extends('layouts.publik')
@section('judul', $tautan->judul)
@section('isi')
    <h1>{{ $tautan->judul }}</h1>
    <p>Buka tautan ini di peramban Anda untuk melanjutkan: <strong>{{ $tautan->url_pendek }}</strong></p>
@endsection
