<?php

namespace App\Rules;

use App\Exceptions\UrlTujuanTidakValid;
use App\Models\User;
use App\Support\Tujuan\ValidatorUrlTujuan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UrlTujuanValid implements ValidationRule
{
    public function __construct(private readonly ?User $oleh = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            app(ValidatorUrlTujuan::class)->validasi((string) $value, $this->oleh);
        } catch (UrlTujuanTidakValid $e) {
            $fail($e->getMessage());
        }
    }
}
