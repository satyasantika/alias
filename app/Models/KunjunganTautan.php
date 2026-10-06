<?php

namespace App\Models;

use App\Enums\JenisPerangkat;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property JenisPerangkat $jenis_perangkat */
class KunjunganTautan extends Model
{
    use HasUuids;

    protected $table = 'kunjungan_tautan';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dikunjungi_pada' => 'datetime',
            'jenis_perangkat' => JenisPerangkat::class,
            'bot' => 'boolean',
        ];
    }

    /** @return BelongsTo<TautanPendek, $this> */
    public function tautan(): BelongsTo
    {
        return $this->belongsTo(TautanPendek::class, 'tautan_pendek_id')->withTrashed();
    }
}
