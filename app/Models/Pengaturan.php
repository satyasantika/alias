<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Pengaturan extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'pengaturan';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['kunci', 'nilai', 'tipe', 'keterangan', 'diubah_oleh'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(\App\Support\Pengaturan::KUNCI_CACHE));
        static::deleted(fn () => Cache::forget(\App\Support\Pengaturan::KUNCI_CACHE));
    }

    protected function namaLog(): string
    {
        return 'pengaturan';
    }
}
