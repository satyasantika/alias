<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

enum CaraCocok: string implements HasLabel
{
    case Persis = 'persis';
    case Awalan = 'awalan';
    case Mengandung = 'mengandung';

    public function getLabel(): string
    {
        return match ($this) {
            self::Persis => 'Persis sama',
            self::Awalan => 'Diawali',
            self::Mengandung => 'Mengandung',
        };
    }

    public function cocok(string $slug, string $pola): bool
    {
        $slug = Str::lower($slug);
        $pola = Str::lower($pola);

        return match ($this) {
            self::Persis => $slug === $pola,
            self::Awalan => str_starts_with($slug, $pola),
            self::Mengandung => str_contains($slug, $pola),
        };
    }
}
