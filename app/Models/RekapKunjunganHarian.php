<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RekapKunjunganHarian extends Model
{
    use HasUuids;

    protected $table = 'rekap_kunjungan_harian';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }
}
