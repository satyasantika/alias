<?php

use App\Enums\Peran;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;

it('menampilkan landing page representatif dengan nilai jual dan tombol aksi', function () {
    $r = $this->get('/');

    $r->assertOk()
        ->assertSee('Alias FKIP')
        ->assertSee('Masuk')
        ->assertSee('Minta akses')
        ->assertSee('Laporkan')
        ->assertSee('Privasi')
        ->assertSee('Dimiliki unit atau pegawai')
        ->assertSee('Tetap berlaku saat pindah tugas')
        ->assertSee('Statistik klik anonim');
});

it('menampilkan halaman 404 representatif dengan tombol kembali dan beranda', function () {
    $r = $this->get('/panel/tidak-ada/sub-jalur');

    $r->assertStatus(404)
        ->assertSee('404')
        ->assertSee('Halaman tidak ditemukan')
        ->assertSee('Kembali')
        ->assertSee('Ke beranda');
});

it('menampilkan halaman 403 representatif saat akses panel ditolak', function () {
    $this->seed(PeranDanIzinSeeder::class);
    $pengguna = User::factory()->create()->assignRole(Peran::Pengguna->value);

    $r = $this->actingAs($pengguna)->get('/horizon');

    $r->assertStatus(403)->assertSee('403')->assertSee('Kembali')->assertSee('Ke beranda');
});
