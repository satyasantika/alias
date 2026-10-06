<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum KodeRedirect: int implements HasLabel
{
    case Sementara = 302;
    case Permanen = 301;

    public function getLabel(): string
    {
        return match ($this) {
            self::Sementara => '302 Sementara (disarankan)',
            self::Permanen => '301 Permanen (klik berikutnya tidak tercatat)',
        };
    }
}
