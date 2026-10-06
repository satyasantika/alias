// Mengambil tangkapan layar panduan dari instance demo (DemoSeeder).
// docker run --rm --network <jaringan> -v $PWD/tools/panduan:/skrip -v <data>:/data -e BASE=http://alias-shot-nginx \
//   mcr.microsoft.com/playwright:v1.49.1-noble sh -c 'cd /skrip && npm i playwright@1.49.1 --no-audit --no-fund && node tangkap.js'
const { chromium } = require('playwright');
const crypto = require('crypto');
const fs = require('fs');

const BASE = process.env.BASE || 'http://alias-shot-nginx';
const OUT = process.env.OUT || '/data/img';
const D = JSON.parse(fs.readFileSync('/data/data.json'));
const SANDI = process.env.SANDI || 'PanduanUji2026';
fs.mkdirSync(OUT, { recursive: true });

function totp(secret) {
  const abj = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  let bits = '';
  for (const c of secret.replace(/=+$/, '')) bits += abj.indexOf(c).toString(2).padStart(5, '0');
  const kunci = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
  const langkah = Math.floor(Date.now() / 30000);
  const buf = Buffer.alloc(8); buf.writeBigUInt64BE(BigInt(langkah));
  const h = crypto.createHmac('sha1', kunci).update(buf).digest();
  const o = h[h.length - 1] & 0xf;
  const kode = ((h[o] & 0x7f) << 24 | h[o + 1] << 16 | h[o + 2] << 8 | h[o + 3]) % 1000000;
  return String(kode).padStart(6, '0');
}

async function masuk(browser, surel) {
  await new Promise((r) => setTimeout(r, 14000)); // pembatas login Filament: 5 percobaan/menit per IP
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 780 }, locale: 'id-ID', timezoneId: 'Asia/Jakarta' });
  const page = await ctx.newPage();
  await page.goto(BASE + '/panel/login');
  await page.fill('input[type="email"]', surel);
  await page.fill('input[type="password"]', SANDI);
  await page.click('button[type="submit"]');
  await page.waitForTimeout(1500);
  const rahasia = D.secrets[surel];
  if (rahasia) {
    const kolom = page.locator('input[autocomplete="one-time-code"], input[type="text"]:visible').first();
    if (await kolom.count()) {
      await kolom.fill(totp(rahasia));
      await page.click('button[type="submit"]');
    }
  }
  await page.waitForURL((u) => !u.pathname.includes('login') && u.pathname.startsWith('/panel'), { timeout: 20000 });
  await page.waitForTimeout(800);
  return { ctx, page };
}

// Garis merah + nomor pada elemen penting.
async function tandai(page, selector, nomor) {
  await page.evaluate(([s, n]) => {
    const el = document.querySelector(s);
    if (!el) return;
    el.style.outline = '3px solid #dc2626'; el.style.outlineOffset = '2px';
    const b = document.createElement('div');
    b.textContent = n;
    b.style.cssText = 'position:absolute;z-index:99999;background:#dc2626;color:#fff;font:700 14px system-ui;border-radius:999px;width:24px;height:24px;display:flex;align-items:center;justify-content:center';
    const r = el.getBoundingClientRect();
    b.style.left = (window.scrollX + r.left - 12) + 'px'; b.style.top = (window.scrollY + r.top - 12) + 'px';
    document.body.appendChild(b);
  }, [selector, String(nomor)]);
}

const peran = {
  umum: { surel: null, foto: [
    ['umum-beranda', '/', {}], ['umum-login', '/panel/login', {}], ['umum-minta-akses', '/minta-akses', {}], ['umum-pratinjau', '/Demo001+', {}], ['umum-lapor', '/lapor?kode=uat-contoh', {}],
  ] },
  pengguna: { surel: 'dosen.a@unsil.ac.id', foto: [
    ['pengguna-dasbor', '/panel', { tunggu: 2500 }],
    ['pengguna-daftar', '/panel/tautan', {}],
    ['pengguna-buat', '/panel/tautan/create', { isi: { 'input[placeholder^="https"]': 'https://docs.google.com/forms/d/e/contoh/viewform' }, tandai: ['input[placeholder^="https"]'] }],
    ['pengguna-statistik', `/panel/tautan/${D.tautan}`, { tunggu: 3500, penuh: true }],
    ['pengguna-notifikasi', '/panel', { klik: 'button[aria-label*="otifikasi"], .fi-icon-btn.fi-topbar-database-notifications-btn', tunggu: 1500 }],
  ] },
  pengelola: { surel: 'pengelola.pmat@unsil.ac.id', foto: [
    ['pengelola-dasbor', '/panel', { tunggu: 3000 }],
    ['pengelola-daftar', '/panel/tautan', {}],
    ['pengelola-unit', '/panel/unit', {}],
    ['pengelola-anggota', `/panel/unit/${D.unit}`, { tunggu: 1500, penuh: true }],
    ['pengelola-pengguna', '/panel/pengguna', {}],
    ['pengelola-statistik', '/panel/statistik-fakultas', { tunggu: 3500, penuh: true }],
  ] },
  admin: { surel: 'admin.alias@unsil.ac.id', foto: [
    ['admin-dasbor', '/panel', { tunggu: 3000 }],
    ['admin-persetujuan', '/panel/tautan/persetujuan', {}],
    ['admin-laporan', '/panel/laporan-penyalahgunaan', { tunggu: 1200 }],
    ['admin-akses', '/panel/permintaan-akses', {}],
    ['admin-pengguna', '/panel/pengguna', {}],
    ['admin-slug', '/panel/slug-terlarang', {}],
    ['admin-domain', '/panel/aturan-domain', {}],
    ['admin-impor', '/panel/tautan', { klikTeks: 'Impor CSV', tunggu: 1500 }],
    ['admin-aktivitas', '/panel/log-aktivitas', {}],
    ['admin-statistik', '/panel/statistik-fakultas', { tunggu: 3500, penuh: true }],
  ] },
  pemantau: { surel: 'wakil.dekan@unsil.ac.id', foto: [
    ['pemantau-dasbor', '/panel', { tunggu: 3500 }],
    ['pemantau-statistik', '/panel/statistik-fakultas', { tunggu: 3500, penuh: true }],
    ['pemantau-ditolak', '/panel/tautan', {}],
  ] },
  super: { surel: 'superadmin@unsil.ac.id', foto: [
    ['super-pengaturan', '/panel/pengaturan-sistem', { penuh: true }],
    ['super-log-login', '/panel/log-login', {}],
    ['super-profil', '/panel/profile', { penuh: true }],
    ['super-dasbor', '/panel', { tunggu: 3000 }],
  ] },
};

(async () => {
  const browser = await chromium.launch();
  for (const [nama, def] of Object.entries(peran)) {
    let ctx, page;
    if (def.surel) ({ ctx, page } = await masuk(browser, def.surel));
    else { ctx = await browser.newContext({ viewport: { width: 1280, height: 780 }, locale: 'id-ID' }); page = await ctx.newPage(); }

    for (const [berkas, path, opsi] of def.foto) {
      try {
        await page.goto(BASE + path, { waitUntil: 'networkidle' });
        await page.waitForTimeout(opsi.tunggu || 900);
        if (opsi.penuh) { // memicu widget lazy-load lalu kembali ke atas
          await page.evaluate(async () => { for (let y = 0; y < document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise((r) => setTimeout(r, 250)); } window.scrollTo(0, 0); });
          await page.waitForTimeout(2500);
        }
        for (const [sel, nilai] of Object.entries(opsi.isi || {})) await page.fill(sel, nilai);
        if (opsi.klik) await page.click(opsi.klik).catch(() => {});
        if (opsi.klikTeks) await page.getByText(opsi.klikTeks, { exact: false }).first().click().catch(() => {});
        if (opsi.klik || opsi.klikTeks) await page.waitForTimeout(opsi.tunggu || 900);
        (opsi.tandai || []).forEach(async () => {});
        for (let i = 0; i < (opsi.tandai || []).length; i++) await tandai(page, opsi.tandai[i], i + 1);
        await page.screenshot({ path: `${OUT}/${berkas}.jpg`, type: 'jpeg', quality: 72, fullPage: !!opsi.penuh });
        console.log('ok', berkas);
      } catch (e) { console.log('GAGAL', berkas, e.message.split('\n')[0]); }
    }
    await ctx.close();
  }
  await browser.close();
})();
