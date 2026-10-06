<?php

namespace App\Rules;

use App\Models\Unit;
use App\Models\User;
use App\Support\Kode\PemeriksaSlug;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SlugKustomValid implements ValidationRule
{
    public function __construct(
        private readonly ?User $pembuat = null,
        private readonly ?Unit $untukUnit = null,
        private readonly ?string $kecualiId = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $pesan = app(PemeriksaSlug::class)->periksa((string) $value, $this->pembuat, $this->untukUnit, $this->kecualiId);

        if ($pesan !== null) {
            $fail($pesan);
        }
    }
}
