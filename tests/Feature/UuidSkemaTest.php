<?php

use App\Models\Activity;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gerbang skema UUID (STANDAR-TEKNIS §4a): tipe kolom mengikuti Blueprint Laravel 13
 * (MariaDB >= 10.7: tipe UUID native; driver lain: char(36)). Khusus MariaDB/MySQL (information_schema).
 */
const TIPE_UUID = ['uuid', 'char(36)'];

// tabel kerangka kerja dengan kunci non-UUID yang diizinkan (§4a butir 6)
const TABEL_KUNCI_BUKAN_UUID = ['migrations', 'jobs', 'failed_jobs', 'job_batches', 'sessions'];

beforeEach(function () {
    if (! in_array(DB::getDriverName(), ['mariadb', 'mysql'], true)) {
        $this->markTestSkipped('Gerbang skema UUID membutuhkan MariaDB/MySQL (information_schema).');
    }
});

/**
 * Satu query untuk seluruh skema: Schema::getColumns()/getForeignKeys() per tabel memicu puluhan query
 * information_schema yang lambat di MariaDB.
 *
 * @return array<string, array<string, string>> [tabel => [kolom => tipe]]
 */
function petaTipeKolom(): array
{
    $peta = [];

    foreach (DB::select(
        'SELECT TABLE_NAME AS tabel, COLUMN_NAME AS kolom, COLUMN_TYPE AS tipe
         FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE()'
    ) as $baris) {
        $peta[$baris->tabel][$baris->kolom] = strtolower($baris->tipe);
    }

    return $peta;
}

it('memakai tipe uuid pada kolom id semua tabel aplikasi', function () {
    foreach (petaTipeKolom() as $tabel => $kolom) {
        if (in_array($tabel, TABEL_KUNCI_BUKAN_UUID, true) || ! array_key_exists('id', $kolom)) {
            continue; // tabel kerangka kerja, pivot, atau tabel tanpa kolom id
        }

        expect($kolom['id'])->toBeIn(TIPE_UUID, "{$tabel}.id bertipe {$kolom['id']}");
    }
});

it('menyamakan tipe kolom foreign key dengan kolom yang diacunya', function () {
    $peta = petaTipeKolom();

    $fk = DB::select(
        'SELECT TABLE_NAME AS tabel, COLUMN_NAME AS kolom, REFERENCED_TABLE_NAME AS acuan_tabel,
                REFERENCED_COLUMN_NAME AS acuan_kolom
         FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL'
    );

    expect($fk)->not->toBeEmpty();

    foreach ($fk as $b) {
        expect($peta[$b->tabel][$b->kolom])->toBe(
            $peta[$b->acuan_tabel][$b->acuan_kolom],
            "FK {$b->tabel}.{$b->kolom} ({$peta[$b->tabel][$b->kolom]}) tidak sama dengan {$b->acuan_tabel}.{$b->acuan_kolom}"
        );
    }
});

it('mengurutkan 100 id UUIDv7 sesuai urutan pembuatan pada ORDER BY id', function () {
    $dibuat = collect(range(1, 100))->map(fn () => User::factory()->create()->id);

    expect(User::query()->orderBy('id')->pluck('id')->all())->toBe($dibuat->all());
});

it('menyimpan relasi polimorfik dengan id UUID', function () {
    $pengguna = User::factory()->create();

    activity()->performedOn($pengguna)->log('uji-uuid');

    $aktivitas = Activity::query()->where('subject_id', $pengguna->id)->firstOrFail();

    expect($aktivitas->subject_id)->toBe($pengguna->id)
        ->and($aktivitas->subject->is($pengguna))->toBeTrue();
});

it('membangkitkan UUID versi 7 untuk pengguna', function () {
    $user = User::factory()->create();

    expect(Str::isUuid($user->id))->toBeTrue()
        ->and($user->id[14])->toBe('7');
});

it('menjalankan route model binding dengan UUID', function () {
    $user = User::factory()->create();

    expect(User::query()->whereKey($user->id)->firstOrFail()->is($user))->toBeTrue();
    expect($user->getKeyType())->toBe('string')->and($user->getIncrementing())->toBeFalse();
});
