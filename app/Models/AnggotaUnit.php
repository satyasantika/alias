<?php

namespace App\Models;

use App\Enums\PeranUnit;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/** @property PeranUnit $peran_unit */
class AnggotaUnit extends Pivot
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'anggota_unit';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['peran_unit' => PeranUnit::class];
    }

    protected function namaLog(): string
    {
        return 'unit';
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
