<?php

namespace App\Models;

use App\Enums\Izin;
use App\Enums\JenisKepemilikan;
use App\Enums\KodeRedirect;
use App\Enums\StatusCekTujuan;
use App\Enums\StatusEfektifTautan;
use App\Enums\StatusTautan;
use App\Models\Concerns\TercatatAktivitas;
use Carbon\CarbonInterface;
use Database\Factories\TautanPendekFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property StatusTautan $status
 * @property JenisKepemilikan $jenis_kepemilikan
 * @property StatusCekTujuan $status_cek_tujuan
 * @property CarbonInterface|null $aktif_mulai
 * @property CarbonInterface|null $aktif_sampai
 * @property CarbonInterface|null $dipakai_pada
 * @property CarbonInterface|null $pertama_aktif_pada
 * @property int|null $batas_klik
 * @property int $jumlah_klik
 * @property bool $kode_kustom
 * @property string $kode
 * @property string $judul
 * @property string $url_tujuan
 * @property string|null $pemilik_id
 * @property string $dibuat_oleh
 * @property bool $sekali_pakai
 * @property int $kode_status_redirect
 */
class TautanPendek extends Model
{
    /** @use HasFactory<TautanPendekFactory> */
    use HasFactory, HasUuids, SoftDeletes, TercatatAktivitas;

    protected $table = 'tautan_pendek';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $attributes = [
        'status' => 'aktif',
        'jenis_kepemilikan' => 'pribadi',
        'kode_status_redirect' => 302,
        'catat_kunjungan' => true,
        'jumlah_klik' => 0,
        'sekali_pakai' => false,
        'teruskan_query' => false,
        'kode_kustom' => false,
    ];

    /**
     * `status`, `jumlah_klik`, dan penanda cek tujuan sengaja tidak mass-assignable:
     * status hanya berubah lewat Action (BR-26), penghitung lewat pencatat.
     */
    protected $fillable = [
        'kode', 'kode_kustom', 'judul', 'keterangan', 'url_tujuan', 'host_tujuan', 'url_tujuan_hash',
        'jenis_kepemilikan', 'pemilik_id', 'unit_id', 'dibuat_oleh', 'aktif_mulai', 'aktif_sampai',
        'batas_klik', 'sekali_pakai', 'teruskan_query', 'catat_kunjungan', 'kode_status_redirect',
    ];

    protected $hidden = ['kata_sandi_hash'];

    protected function casts(): array
    {
        return [
            'status' => StatusTautan::class,
            'jenis_kepemilikan' => JenisKepemilikan::class,
            'status_cek_tujuan' => StatusCekTujuan::class,
            'kode_kustom' => 'boolean',
            'sekali_pakai' => 'boolean',
            'teruskan_query' => 'boolean',
            'catat_kunjungan' => 'boolean',
            'kode_status_redirect' => 'integer',
            'jumlah_klik' => 'integer',
            'pertama_aktif_pada' => 'datetime',
            'disetujui_pada' => 'datetime',
            'aktif_mulai' => 'datetime',
            'aktif_sampai' => 'datetime',
            'dipakai_pada' => 'datetime',
            'klik_terakhir_pada' => 'datetime',
            'dicek_tujuan_pada' => 'datetime',
        ];
    }

    protected function namaLog(): string
    {
        return 'tautan';
    }

    /** @return list<string> */
    protected function atributTambahanTidakDicatat(): array
    {
        return ['jumlah_klik', 'klik_terakhir_pada', 'dipakai_pada', 'dicek_tujuan_pada', 'kode_http_terakhir', 'gagal_cek_beruntun', 'status_cek_tujuan'];
    }

    // ---- Relasi -------------------------------------------------------------------------------

    /** @return BelongsTo<User, $this> */
    public function pemilik(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemilik_id');
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** @return HasMany<RiwayatStatusTautan, $this> */
    public function riwayatStatus(): HasMany
    {
        return $this->hasMany(RiwayatStatusTautan::class)->latest('created_at');
    }

    /** @return HasMany<RiwayatKepemilikanTautan, $this> */
    public function riwayatKepemilikan(): HasMany
    {
        return $this->hasMany(RiwayatKepemilikanTautan::class)->latest('created_at');
    }

    // ---- Akses --------------------------------------------------------------------------------

    /**
     * BR-20: pengguna → pribadi miliknya + unit tempat ia anggota; admin → semua; pemantau (tanpa tautan.lihat) → kosong.
     *
     * @param  Builder<TautanPendek>  $query
     * @return Builder<TautanPendek>
     */
    public function scopeTerlihatOleh(Builder $query, User $user): Builder
    {
        if ($user->adalahAdmin()) {
            return $query;
        }

        if (! $user->can(Izin::TautanLihat->value)) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where(fn (Builder $p) => $p->where('jenis_kepemilikan', JenisKepemilikan::Pribadi->value)->where('pemilik_id', $user->getKey()))
                ->orWhere(fn (Builder $u) => $u->where('jenis_kepemilikan', JenisKepemilikan::Unit->value)
                    ->whereIn('unit_id', $user->unitAnggota()->select('unit.id')));
        });
    }

    // ---- Turunan ------------------------------------------------------------------------------

    /**
     * URL pendek lengkap (domain pendek bila dikonfigurasi, selain itu APP_URL).
     *
     * @return Attribute<non-falsy-string, never>
     */
    protected function urlPendek(): Attribute
    {
        return Attribute::get(function (): string {
            $domain = config('alias.domain_pendek');

            if (filled($domain)) {
                $skema = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

                return "{$skema}://{$domain}/{$this->kode}";
            }

            return rtrim((string) config('app.url'), '/').'/'.$this->kode;
        });
    }

    /** BR-10: status efektif (tidak disimpan). Pemeriksaan kata sandi (BR-35) dilakukan di pengalihan. */
    public function statusEfektif(?CarbonInterface $sekarang = null): StatusEfektifTautan
    {
        $sekarang ??= now();

        if ($this->trashed() || $this->status !== StatusTautan::Aktif) {
            return StatusEfektifTautan::TidakTersedia;
        }

        if ($this->aktif_mulai !== null && $this->aktif_mulai->greaterThan($sekarang)) {
            return StatusEfektifTautan::Terjadwal;
        }

        if ($this->aktif_sampai !== null && $this->aktif_sampai->lessThanOrEqualTo($sekarang)) {
            return StatusEfektifTautan::Kedaluwarsa;
        }

        if (($this->batas_klik !== null && $this->jumlah_klik >= $this->batas_klik)
            || ($this->sekali_pakai && $this->dipakai_pada !== null)) {
            return StatusEfektifTautan::Habis;
        }

        return StatusEfektifTautan::DapatDialihkan;
    }

    public function kodeRedirect(): KodeRedirect
    {
        return KodeRedirect::from((int) $this->kode_status_redirect);
    }

    public function milikPribadi(User $user): bool
    {
        return $this->jenis_kepemilikan === JenisKepemilikan::Pribadi && $this->pemilik_id === $user->getKey();
    }
}
