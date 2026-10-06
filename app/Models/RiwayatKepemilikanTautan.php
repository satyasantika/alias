<?php

namespace App\Models;

use App\Enums\JenisKepemilikan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property JenisKepemilikan $dari_jenis
 * @property JenisKepemilikan $ke_jenis
 */
class RiwayatKepemilikanTautan extends Model
{
    use HasUuids;

    protected $table = 'riwayat_kepemilikan_tautan';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dari_jenis' => JenisKepemilikan::class,
            'ke_jenis' => JenisKepemilikan::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TautanPendek, $this> */
    public function tautan(): BelongsTo
    {
        return $this->belongsTo(TautanPendek::class, 'tautan_pendek_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function pelaku(): BelongsTo
    {
        return $this->belongsTo(User::class, 'oleh');
    }
}
