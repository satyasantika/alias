<?php

namespace App\Models;

use App\Enums\StatusTautan;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property StatusTautan|null $dari_status
 * @property StatusTautan $ke_status
 */
class RiwayatStatusTautan extends Model
{
    use HasUuids;

    protected $table = 'riwayat_status_tautan';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'dari_status' => StatusTautan::class,
            'ke_status' => StatusTautan::class,
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
