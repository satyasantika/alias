<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('judul', 'Alias FKIP') — Alias FKIP</title>
    <style>
        :root { --biru:#1d4ed8; --teks:#111827; --abu:#6b7280; --garis:#d1d5db; --latar:#f3f4f6; --merah:#b91c1c; --hijau:#047857; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color:var(--teks); background:var(--latar); line-height:1.5; }
        header, footer { padding:1rem; text-align:center; color:var(--abu); font-size:.875rem; }
        header a { color:var(--biru); font-weight:600; text-decoration:none; font-size:1.125rem; }
        main { max-width:36rem; margin:1rem auto; background:#fff; padding:1.5rem; border-radius:.75rem; box-shadow:0 1px 3px rgba(0,0,0,.1); }
        main.lebar { max-width:56rem; }
        h1 { font-size:1.375rem; margin:0 0 .5rem; }
        label { display:block; margin:.875rem 0 .25rem; font-weight:600; }
        input, select, textarea { width:100%; padding:.625rem .75rem; border:1px solid var(--garis); border-radius:.5rem; font:inherit; min-height:44px; }
        input[type=checkbox] { width:auto; min-height:0; margin-right:.5rem; }
        button, .tombol { display:inline-block; margin-top:1.25rem; padding:.75rem 1.25rem; min-height:44px; border:0; border-radius:.5rem; background:var(--biru); color:#fff; font:inherit; font-weight:600; cursor:pointer; text-decoration:none; }
        .tombol-garis { background:transparent; color:var(--biru); border:1px solid var(--biru); margin-left:.5rem; }
        .galat { color:var(--merah); font-size:.875rem; margin-top:.25rem; }
        .info { background:#ecfdf5; border:1px solid #a7f3d0; color:var(--hijau); padding:.75rem 1rem; border-radius:.5rem; }
        .bantu { color:var(--abu); font-size:.875rem; }
        .sembunyi { position:absolute; left:-9999px; height:0; overflow:hidden; }
        :focus-visible { outline:3px solid #f59e0b; outline-offset:2px; }
        .kode-galat { font-size:3.5rem; font-weight:700; color:var(--biru); margin:0 0 .25rem; line-height:1; }
        .hero { text-align:center; padding:.5rem 0 1.75rem; border-bottom:1px solid var(--garis); margin-bottom:1.75rem; }
        .hero .label { color:var(--biru); font-weight:600; font-size:.8125rem; text-transform:uppercase; letter-spacing:.05em; margin:0 0 .5rem; }
        .hero h1 { font-size:1.75rem; line-height:1.3; margin:0 0 .75rem; }
        .hero .lead { color:var(--abu); max-width:40rem; margin:0 auto 1.25rem; }
        .fitur { display:grid; grid-template-columns:repeat(auto-fit,minmax(13rem,1fr)); gap:1rem; margin-bottom:1.75rem; }
        .kad { background:var(--latar); border:1px solid var(--garis); border-radius:.625rem; padding:1rem 1.125rem; }
        .kad h3 { margin:0 0 .375rem; font-size:1rem; }
        .kad p { margin:0; color:var(--abu); font-size:.875rem; }
        .panduan { border-top:1px solid var(--garis); padding-top:1.5rem; }
        .panduan h2 { font-size:1.125rem; margin:0 0 .25rem; }
    </style>
</head>
<body>
    <header><a href="{{ url('/') }}">Alias FKIP</a><br>Pemendek tautan resmi Fakultas Keguruan dan Ilmu Pendidikan</header>
    <main class="@yield('kelas-main')">@yield('isi')</main>
    <footer><a href="{{ url('/privasi') }}">Privasi</a> · <a href="{{ url('/lapor') }}">Laporkan penyalahgunaan</a></footer>
</body>
</html>
