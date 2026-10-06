<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MetodeLogin: string implements HasLabel
{
    case KataSandi = 'kata_sandi';
    case Google = 'google';

    public function getLabel(): string
    {
        return match ($this) {
            self::KataSandi => 'Kata sandi',
            self::Google => 'Google',
        };
    }
}
