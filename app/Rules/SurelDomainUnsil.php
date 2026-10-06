<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/** BR-32: surel wajib berakhiran domain persis yang diizinkan (bukan sekadar mengandung). */
class SurelDomainUnsil implements ValidationRule
{
    public static function lolos(?string $surel): bool
    {
        if (! is_string($surel) || substr_count($surel, '@') !== 1) {
            return false;
        }

        $domain = Str::lower(Str::after($surel, '@'));

        return in_array($domain, array_map(Str::lower(...), config('alias.domain_surel')), true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::lolos(is_string($value) ? trim($value) : null)) {
            $fail('Surel harus memakai domain @'.implode(' atau @', config('alias.domain_surel')).'.');
        }
    }
}
