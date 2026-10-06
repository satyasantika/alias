<?php

namespace App\Models;

use App\Enums\DimensiRekap;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** @property DimensiRekap $dimensi */
class RekapKunjunganDimensi extends Model
{
    use HasUuids;

    protected $table = 'rekap_kunjungan_dimensi';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'dimensi' => DimensiRekap::class];
    }
}
