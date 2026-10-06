<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Jejak audit seragam: hanya perubahan, tanpa kata sandi/hash/rahasia MFA (PRD §9 Audit).
 * Model dapat menimpa namaLog() dan atributTidakDicatat().
 */
trait TercatatAktivitas
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept($this->atributTidakDicatat())
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName($this->namaLog());
    }

    protected function namaLog(): string
    {
        return 'sistem';
    }

    /** @return list<string> */
    protected function atributTidakDicatat(): array
    {
        return [
            'password', 'remember_token', 'kata_sandi_hash',
            'app_authentication_secret', 'app_authentication_recovery_codes',
            'updated_at', 'created_at',
        ];
    }
}
