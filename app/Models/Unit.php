<?php

namespace App\Models;

use App\Enums\JenisUnit;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property JenisUnit $jenis
 * @property bool $aktif
 */
class Unit extends Model
{
    use HasUuids, SoftDeletes, TercatatAktivitas;

    protected $table = 'unit';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $attributes = ['aktif' => true];

    protected $fillable = [
        'kode', 'nama', 'jenis', 'induk_id', 'prefiks_slug', 'kuota_tautan', 'aktif', 'kode_eksternal',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => JenisUnit::class,
            'aktif' => 'boolean',
        ];
    }

    protected function namaLog(): string
    {
        return 'unit';
    }

    /** @return BelongsTo<Unit, $this> */
    public function induk(): BelongsTo
    {
        return $this->belongsTo(self::class, 'induk_id');
    }

    /** @return HasMany<Unit, $this> */
    public function anak(): HasMany
    {
        return $this->hasMany(self::class, 'induk_id');
    }

    /** @return HasMany<AnggotaUnit, $this> */
    public function keanggotaan(): HasMany
    {
        return $this->hasMany(AnggotaUnit::class);
    }

    /** @return BelongsToMany<User, $this, AnggotaUnit> */
    public function anggota(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'anggota_unit')->using(AnggotaUnit::class)->withPivot('peran_unit');
    }

    public function jumlahPengelola(): int
    {
        return $this->keanggotaan()->where('peran_unit', 'pengelola')->count();
    }
}
