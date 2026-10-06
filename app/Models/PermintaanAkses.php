<?php

namespace App\Models;

use App\Enums\StatusPermintaanAkses;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property StatusPermintaanAkses $status */
class PermintaanAkses extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'permintaan_akses';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => StatusPermintaanAkses::class,
            'email_terverifikasi_pada' => 'datetime',
            'diproses_pada' => 'datetime',
        ];
    }

    protected function namaLog(): string
    {
        return 'akses';
    }

    /** @return list<string> */
    protected function atributTambahanTidakDicatat(): array
    {
        return ['ip_hash'];
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
