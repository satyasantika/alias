<?php

namespace App\Models;

use App\Enums\KategoriLaporan;
use App\Enums\StatusLaporan;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property KategoriLaporan $kategori
 * @property StatusLaporan $status
 */
class LaporanPenyalahgunaan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'laporan_penyalahgunaan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $attributes = ['status' => 'baru'];

    protected $guarded = [];

    protected $hidden = ['ip_hash'];

    protected function casts(): array
    {
        return [
            'kategori' => KategoriLaporan::class,
            'status' => StatusLaporan::class,
            'ditangani_pada' => 'datetime',
        ];
    }

    protected function namaLog(): string
    {
        return 'moderasi';
    }

    /** @return list<string> */
    protected function atributTambahanTidakDicatat(): array
    {
        return ['ip_hash', 'email_pelapor'];
    }

    /** @return BelongsTo<TautanPendek, $this> */
    public function tautan(): BelongsTo
    {
        return $this->belongsTo(TautanPendek::class, 'tautan_pendek_id')->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function penangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }
}
