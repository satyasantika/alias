<?php

/**
 * Membangun panduan HTML mandiri (gambar tertanam base64) ke public/panduan/.
 * php tools/panduan/bangun.php [folder-gambar]
 */
$gambarDir = $argv[1] ?? __DIR__.'/img';
$keluar = dirname(__DIR__, 2).'/public/panduan';
@mkdir($keluar, 0775, true);
$isi = require __DIR__.'/isi.php';

$e = fn (string $s) => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$gambar = function (string $kunci) use ($gambarDir): string {
    $berkas = "{$gambarDir}/{$kunci}.jpg";
    if (! is_file($berkas)) {
        fwrite(STDERR, "Gambar tidak ada: {$berkas}\n");
        exit(1);
    }

    return 'data:image/jpeg;base64,'.base64_encode(file_get_contents($berkas));
};

$css = <<<'CSS'
:root{--biru:#1d4ed8;--teks:#111827;--abu:#6b7280;--garis:#d1d5db;--latar:#f3f4f6;--hijau:#047857;--merah:#b91c1c}
*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;color:var(--teks);background:var(--latar);line-height:1.6}
header{background:#fff;border-bottom:1px solid var(--garis);padding:1rem}header .isi,main{max-width:60rem;margin:0 auto}
header a.merek{font-weight:700;color:var(--biru);text-decoration:none;font-size:1.125rem}
nav{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.75rem}nav a{padding:.5rem .875rem;border:1px solid var(--garis);border-radius:999px;color:var(--teks);text-decoration:none;font-size:.875rem;min-height:44px;display:inline-flex;align-items:center}
nav a[aria-current=page]{background:var(--biru);border-color:var(--biru);color:#fff}
main{padding:1rem}h1{margin:.5rem 0}h2{margin-top:2rem}.kartu{background:#fff;border-radius:.75rem;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:1.25rem;margin:1rem 0}
.dua{display:grid;grid-template-columns:1fr 1fr;gap:1rem}@media(max-width:640px){.dua{grid-template-columns:1fr}}
.ya li::marker{color:var(--hijau)}.tidak li::marker{color:var(--merah)}
ol.langkah{list-style:none;padding:0;counter-reset:l}ol.langkah>li{counter-increment:l;background:#fff;border-radius:.75rem;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:1.25rem;margin:1rem 0}
ol.langkah h3{margin:0 0 .5rem;display:flex;gap:.625rem;align-items:center}ol.langkah h3::before{content:counter(l);background:var(--biru);color:#fff;border-radius:999px;min-width:1.75rem;height:1.75rem;display:inline-flex;align-items:center;justify-content:center;font-size:.875rem}
figure{margin:.75rem 0 0}figure img{width:100%;height:auto;border:1px solid var(--garis);border-radius:.5rem;display:block}figcaption{font-size:.8125rem;color:var(--abu);margin-top:.25rem}
.tip{background:#eff6ff;border:1px solid #bfdbfe;border-radius:.5rem;padding:.75rem 1rem}a{color:var(--biru)}footer{text-align:center;color:var(--abu);font-size:.875rem;padding:2rem 1rem}
:focus-visible{outline:3px solid #f59e0b;outline-offset:2px}
CSS;

// Panduan super-admin sengaja TIDAK terdaftar: tidak muncul di navigasi, indeks, maupun landing page.
$nama = ['index' => 'Beranda panduan', 'pengguna' => 'Pengguna', 'pengelola' => 'Pengelola unit', 'admin' => 'Admin Alias', 'pemantau' => 'Pemantau'];
$navigasi = function (string $aktif) use ($nama, $e): string {
    $h = '<nav aria-label="Panduan per peran">';
    foreach ($nama as $kunci => $label) {
        $h .= '<a href="'.($kunci === 'index' ? './' : $kunci.'.html').'"'.($kunci === $aktif ? ' aria-current="page"' : '').'>'.$e($label).'</a>';
    }

    return $h.'</nav>';
};
$halaman = function (string $judul, string $aktif, string $badan) use ($css, $navigasi, $e): string {
    return '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<meta name="robots" content="noindex"><title>'.$e($judul).' — Alias FKIP</title><style>'.$css.'</style></head><body>'
        .'<header><div class="isi"><a class="merek" href="../">Alias FKIP</a> · <span>Panduan</span>'.$navigasi($aktif).'</div></header>'
        .'<main>'.$badan.'</main><footer>Alias FKIP — Fakultas Keguruan dan Ilmu Pendidikan Universitas Siliwangi · Panduan v1.0.0 · <a href="../">Kembali ke beranda</a></footer></body></html>';
};

foreach ($isi as $kunci => $p) {
    $b = '<h1>'.$e($p['judul']).'</h1><p>'.$e($p['siapa']).'</p><div class="dua">';
    $b .= '<section class="kartu ya"><h2 style="margin-top:0">Yang dapat Anda lakukan</h2><ul class="ya">'.implode('', array_map(fn ($x) => '<li>'.$e($x).'</li>', $p['bisa'])).'</ul></section>';
    $b .= '<section class="kartu tidak"><h2 style="margin-top:0">Yang tidak dapat Anda lakukan</h2><ul class="tidak">'.implode('', array_map(fn ($x) => '<li>'.$e($x).'</li>', $p['tidak'])).'</ul></section></div>';
    $b .= '<h2>Langkah demi langkah</h2><ol class="langkah">';
    foreach ($p['langkah'] as [$judul, $teks, $img]) {
        $b .= '<li><h3>'.$e($judul).'</h3><p>'.$e($teks).'</p><figure><img src="'.$gambar($img).'" alt="Tangkapan layar: '.$e($judul).'" loading="lazy"><figcaption>'.$e($judul).'</figcaption></figure></li>';
    }
    $b .= '</ol><h2>Tips</h2><div class="tip"><ul>'.implode('', array_map(fn ($x) => '<li>'.$e($x).'</li>', $p['tips'])).'</ul></div>';
    $b .= '<p>Butuh bantuan? Hubungi admin Alias FKIP. <a href="./">Panduan peran lain</a>.</p>';
    file_put_contents("{$keluar}/{$kunci}.html", $halaman($p['judul'], $kunci, $b));
}

$kartu = '';
$ringkas = ['pengguna' => 'Dosen & tendik: buat tautan, QR, statistik.', 'pengelola' => 'Admin prodi/unit: kelola tautan dan anggota unit.', 'admin' => 'Operator: persetujuan, moderasi, pengguna, impor.', 'pemantau' => 'Pimpinan: statistik agregat saja.'];
foreach ($ringkas as $k => $t) {
    $kartu .= '<a class="kartu" style="display:block;text-decoration:none;color:inherit" href="'.$k.'.html"><h2 style="margin:0">'.$e($isi[$k]['judul']).'</h2><p style="margin:.25rem 0 0">'.$e($t).'</p></a>';
}
$b = '<h1>Panduan Alias FKIP</h1><p>Alias FKIP memendekkan tautan panjang menjadi alamat resmi fakultas dan mencatat klik secara anonim. Pilih panduan sesuai peran Anda; setiap langkah dilengkapi tangkapan layar.</p>'.$kartu
    .'<div class="kartu"><h2 style="margin-top:0">Memulai</h2><ol><li>Buka halaman <a href="../panel/login">masuk</a> dan gunakan surel @unsil.ac.id.</li><li>Belum punya akun? <a href="../minta-akses">Minta akses</a>.</li><li>Menerima tautan mencurigakan? <a href="../lapor">Laporkan</a>.</li></ol></div>';
file_put_contents("{$keluar}/index.html", $halaman('Panduan Alias FKIP', 'index', $b));
echo "Panduan dibangun di {$keluar}\n";
