<?php

namespace App\Models;

use App\Enums\Peran;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
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
 */
class User extends Authenticatable implements FilamentUser, HasName
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
