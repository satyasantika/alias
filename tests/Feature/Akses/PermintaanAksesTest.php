<?php

use App\Enums\Peran;
use App\Enums\StatusPermintaanAkses;
use App\Filament\Resources\PermintaanAksesResource\Pages\ListPermintaanAkses;
use App\Models\PermintaanAkses;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\AturKataSandiAkun;
use App\Notifications\PermintaanAksesBaru;
use App\Notifications\PermintaanAksesDiputuskan;
use App\Notifications\VerifikasiSurelPermintaanAkses;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\UnitSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, UnitSeeder::class]);
    Filament::setCurrentPanel('alias');
    RateLimiter::clear('x');
    $this->data = [
        'nama' => 'Calon Dosen', 'email' => 'calon@unsil.ac.id', 'nip' => '1987', 'alasan' => 'Membuat tautan pendaftaran seminar prodi.',
        'setuju' => '1', 'website' => '',
    ];
    $this->admin = User::factory()->create()->assignRole(Peran::AdminAlias->value);
    $this->admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
});

it('menampilkan formulir publik', function () {
    $this->get('/minta-akses')->assertOk()->assertSee('Minta akses Alias FKIP')->assertSee('noindex', false);
});

it('menolak surel di luar domain', function () {
    $this->post('/minta-akses', [...$this->data, 'email' => 'calon@gmail.com'])->assertSessionHasErrors('email');
    expect(PermintaanAkses::count())->toBe(0);
});

it('mewajibkan persetujuan privasi', function () {
    $this->post('/minta-akses', [...$this->data, 'setuju' => null])->assertSessionHasErrors('setuju');
});

it('menyimpan permintaan dan mengirim surel verifikasi', function () {
    Notification::fake();

    $this->post('/minta-akses', $this->data)->assertRedirect(route('akses.formulir'))->assertSessionHas('terkirim');

    $p = PermintaanAkses::firstOrFail();
    expect($p->status)->toBe(StatusPermintaanAkses::MenungguVerifikasiSurel)
        ->and($p->ip_hash)->toHaveLength(64)
        ->and($p->ip_hash)->not->toContain('127.0.0.1');
    Notification::assertSentOnDemand(VerifikasiSurelPermintaanAkses::class);
});

it('mengabaikan diam-diam bila honeypot terisi', function () {
    Notification::fake();

    $this->post('/minta-akses', [...$this->data, 'website' => 'http://spam'])->assertSessionHas('terkirim');

    expect(PermintaanAkses::count())->toBe(0);
    Notification::assertNothingSent();
});

it('tidak membocorkan surel yang sudah terdaftar atau sedang diproses', function () {
    Notification::fake();
    User::factory()->create(['email' => 'calon@unsil.ac.id']);

    $this->post('/minta-akses', $this->data)->assertSessionHas('terkirim');
    expect(PermintaanAkses::count())->toBe(0);
    Notification::assertNothingSent();

    User::where('email', 'calon@unsil.ac.id')->forceDelete();
    $this->post('/minta-akses', $this->data);
    $this->post('/minta-akses', $this->data)->assertSessionHas('terkirim');
    expect(PermintaanAkses::count())->toBe(1);
});

it('memverifikasi surel lewat tautan bertanda tangan dan memberi tahu admin', function () {
    Notification::fake();
    $this->post('/minta-akses', $this->data);
    $p = PermintaanAkses::firstOrFail();

    $url = URL::temporarySignedRoute('akses.verifikasi', now()->addHours(24), ['permintaan' => $p->id]);
    $this->get($url)->assertOk()->assertSee('Surel terverifikasi');

    expect($p->fresh()->status)->toBe(StatusPermintaanAkses::Menunggu)->and($p->fresh()->email_terverifikasi_pada)->not->toBeNull();
    Notification::assertSentTo($this->admin, PermintaanAksesBaru::class);
});

it('menolak tautan verifikasi kedaluwarsa atau tanpa tanda tangan', function () {
    $p = PermintaanAkses::create(['nama' => 'A', 'email' => 'a@unsil.ac.id', 'alasan' => 'x', 'ip_hash' => str_repeat('a', 64)]);

    $kedaluwarsa = URL::temporarySignedRoute('akses.verifikasi', now()->subMinute(), ['permintaan' => $p->id]);
    $this->get($kedaluwarsa)->assertForbidden();
    $this->get('/minta-akses/verifikasi/'.$p->id)->assertForbidden();
    expect($p->fresh()->status)->toBe(StatusPermintaanAkses::MenungguVerifikasiSurel);
});

it('membatasi 3 permintaan per jam dari IP yang sama (429)', function () {
    foreach (range(1, 3) as $i) {
        $this->post('/minta-akses', [...$this->data, 'email' => "c{$i}@unsil.ac.id"])->assertRedirect();
    }

    $this->post('/minta-akses', [...$this->data, 'email' => 'c4@unsil.ac.id'])->assertStatus(429);
});

it('menyetujui permintaan: akun berperan pengguna tanpa kata sandi + surel atur kata sandi', function () {
    Notification::fake();
    $pmat = Unit::where('kode', 'PMAT')->first();
    $p = PermintaanAkses::create([
        'nama' => 'Calon Dosen', 'email' => 'calon@unsil.ac.id', 'alasan' => 'Keperluan seminar', 'ip_hash' => str_repeat('a', 64),
        'status' => StatusPermintaanAkses::Menunggu, 'unit_id' => $pmat->id,
    ]);

    Livewire::actingAs($this->admin)->test(ListPermintaanAkses::class)
        ->callAction(TestAction::make('setujui')->table($p), ['peran' => 'pengguna', 'unit_id' => $pmat->id])
        ->assertHasNoActionErrors();

    $user = User::where('email', 'calon@unsil.ac.id')->firstOrFail();
    expect($user->password)->toBeNull()->and($user->hasRole('pengguna'))->toBeTrue()
        ->and($user->anggotaUnit($pmat))->toBeTrue()
        ->and($p->fresh()->status)->toBe(StatusPermintaanAkses::Disetujui)
        ->and($p->fresh()->user_id)->toBe($user->id);
    Notification::assertSentTo($user, AturKataSandiAkun::class);
    Notification::assertSentOnDemand(PermintaanAksesDiputuskan::class);
});

it('menolak permintaan dengan alasan dan mengirim surel penolakan', function () {
    Notification::fake();
    $p = PermintaanAkses::create([
        'nama' => 'X', 'email' => 'x@unsil.ac.id', 'alasan' => 'Keperluan lain', 'ip_hash' => str_repeat('a', 64),
        'status' => StatusPermintaanAkses::Menunggu,
    ]);

    Livewire::actingAs($this->admin)->test(ListPermintaanAkses::class)
        ->callAction(TestAction::make('tolak')->table($p), ['alasan' => 'Bukan civitas FKIP'])
        ->assertHasNoActionErrors();

    expect($p->fresh()->status)->toBe(StatusPermintaanAkses::Ditolak)->and($p->fresh()->catatan)->toBe('Bukan civitas FKIP');
    Notification::assertSentOnDemand(PermintaanAksesDiputuskan::class);
    expect(User::where('email', 'x@unsil.ac.id')->exists())->toBeFalse();
});

it('membatasi resource hanya untuk pemegang akses.proses', function () {
    $this->actingAs(User::factory()->create()->assignRole(Peran::Pengguna->value))->get('/panel/permintaan-akses')->assertForbidden();
    $this->actingAs($this->admin)->get('/panel/permintaan-akses')->assertOk();
});
