<?php

namespace App\Models;

use App\Enums\JenisKepemilikan;
use App\Enums\Peran;
use App\Enums\PeranUnit;
use App\Enums\StatusTautan;
use App\Models\Concerns\TercatatAktivitas;
use App\Rules\SurelDomainUnsil;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property bool $aktif
 * @property CarbonImmutable|null $terkunci_sampai
 * @property CarbonImmutable|null $terakhir_masuk_pada
 * @property string|null $app_authentication_secret
 * @property array<string>|null $app_authentication_recovery_codes
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes, TercatatAktivitas;

    protected $attributes = [
        'aktif' => true,
    ];

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'email',
        'password',
        'nip',
        'nidn',
        'unit_id',
        'aktif',
        'kuota_tautan',
        'kode_eksternal',
    ];

    // `aktif` diubah lewat formulir pengguna & Action (bukan pengisian massal liar).

    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'aktif' => 'boolean',
            'terkunci_sampai' => 'datetime',
            'terakhir_masuk_pada' => 'datetime',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    public function terkunci(): bool
    {
        return $this->terkunci_sampai !== null && $this->terkunci_sampai->isFuture();
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->aktif && ! $this->terkunci() && SurelDomainUnsil::lolos($this->email);
    }

    protected function namaLog(): string
    {
        return 'pengguna';
    }

    /** @return BelongsToMany<Unit, $this, AnggotaUnit> */
    public function unitAnggota(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'anggota_unit')->using(AnggotaUnit::class)->withPivot('peran_unit');
    }

    /** @return BelongsToMany<Unit, $this, AnggotaUnit> */
    public function unitDikelola(): BelongsToMany
    {
        return $this->unitAnggota()->wherePivot('peran_unit', PeranUnit::Pengelola->value);
    }

    public function kelolaUnit(Unit $unit): bool
    {
        return $this->unitDikelola()->whereKey($unit->getKey())->exists();
    }

    public function anggotaUnit(Unit $unit): bool
    {
        return $this->unitAnggota()->whereKey($unit->getKey())->exists();
    }

    /** Jumlah tautan pribadi berstatus aktif (untuk penonaktifan akun, BR-30). */
    public function jumlahTautanAktif(): int
    {
        return TautanPendek::query()
            ->where('jenis_kepemilikan', JenisKepemilikan::Pribadi->value)
            ->where('pemilik_id', $this->getKey())
            ->where('status', StatusTautan::Aktif->value)
            ->count();
    }

    public function adalahAdmin(): bool
    {
        return $this->wajibMfa();
    }

    public function wajibMfa(): bool
    {
        return $this->hasAnyRole([Peran::SuperAdmin->value, Peran::AdminAlias->value]);
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return array<string>|null */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param  array<string>|null  $codes */
    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function canImpersonate(): bool
    {
        return $this->hasRole(Peran::SuperAdmin->value);
    }

    public function canBeImpersonated(): bool
    {
        return ! $this->hasRole(Peran::SuperAdmin->value);
    }

    public function getFilamentName(): string
    {
        return $this->name ?: $this->email;
    }
}
