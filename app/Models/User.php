<?php

namespace App\Models;

use App\Enums\Peran;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
    use HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

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
        return $this->aktif && ! $this->terkunci();
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
