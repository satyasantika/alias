<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum StatusCekTujuan: string implements HasColor, HasLabel
{
    case Belum = 'belum';
    case Sehat = 'sehat';
    case Terbatas = 'terbatas';
    case Bermasalah = 'bermasalah';

    public function getLabel(): string
    {
        return match ($this) {
            self::Belum => 'Belum dicek',
            self::Sehat => 'Sehat',
            self::Terbatas => 'Terbatas (perlu login)',
            self::Bermasalah => 'Bermasalah',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Belum => 'gray',
            self::Sehat => 'success',
            self::Terbatas => 'warning',
            self::Bermasalah => 'danger',
        };
    }
}
