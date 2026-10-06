@extends('layouts.publik')
@section('judul', 'Privasi')
@section('isi')
    <h1>Pemberitahuan privasi</h1>

    <p style="white-space: pre-line;">{{ $ringkasan }}</p>

    <h2>Data yang dicatat saat tautan dibuka</h2>
    <ul>
        <li>Waktu klik.</li>
        <li>Alamat IP yang <strong>dianonimkan</strong> (oktet terakhir IPv4 dihapus; IPv6 dipotong ke /48) dan hash IP berumur satu hari untuk menghitung pengunjung unik. IP utuh tidak disimpan.</li>
        <li>Jenis peramban dan sistem operasi (versi mayor), jenis perangkat, serta situs perujuk (nama host saja).</li>
        <li>Penanda bila permintaan berasal dari bot/pratinjau.</li>
    </ul>
    <p class="bantu">User agent mentah, path perujuk, bahasa, dan lokasi tidak disimpan.</p>

    <h2>Tujuan</h2>
    <p>Statistik penggunaan layanan bagi pemilik tautan dan pimpinan (agregat), serta keamanan layanan (mendeteksi penyalahgunaan).</p>

    <h2>Retensi</h2>
    <ul>
        <li>Rincian kunjungan pengunjung manusia disimpan <strong>{{ $retensiBulan }} bulan</strong>, lalu hanya ringkasan agregat yang disimpan.</li>
        <li>Rincian kunjungan bot disimpan <strong>{{ $retensiBotHari }} hari</strong>.</li>
        <li>Log masuk akun pengelola disimpan {{ $retensiLogLoginHari }} hari untuk keamanan.</li>
    </ul>

    <h2>Kontak</h2>
    <p>Pertanyaan atau permintaan terkait data pribadi dapat disampaikan kepada admin Alias FKIP melalui unit TI/Humas FKIP Universitas Siliwangi.</p>
@endsection
