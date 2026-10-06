<?php

namespace Database\Factories;

use App\Enums\JenisKepemilikan;
use App\Models\TautanPendek;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<TautanPendek> */
class TautanPendekFactory extends Factory
{
    protected $model = TautanPendek::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $url = 'https://contoh.unsil.ac.id/'.fake()->slug();

        return [
            'kode' => Str::random(7),
            'kode_kustom' => false,
            'judul' => fake()->sentence(3),
            'url_tujuan' => $url,
            'host_tujuan' => 'contoh.unsil.ac.id',
            'url_tujuan_hash' => hash('sha256', $url),
            'jenis_kepemilikan' => JenisKepemilikan::Pribadi,
            'pemilik_id' => User::factory(),
            'unit_id' => null,
            'dibuat_oleh' => fn (array $atribut) => $atribut['pemilik_id'],
        ];
    }

    public function milikPribadi(User $user): static
    {
        return $this->state(fn () => [
            'jenis_kepemilikan' => JenisKepemilikan::Pribadi, 'pemilik_id' => $user->getKey(), 'unit_id' => null, 'dibuat_oleh' => $user->getKey(),
        ]);
    }

    public function milikUnit(Unit $unit, ?User $pembuat = null): static
    {
        return $this->state(fn () => [
            'jenis_kepemilikan' => JenisKepemilikan::Unit, 'pemilik_id' => null, 'unit_id' => $unit->getKey(),
            'dibuat_oleh' => ($pembuat ?? User::factory()->create())->getKey(),
        ]);
    }

    /** Status dipaksakan langsung (di luar Action) khusus untuk data uji. */
    public function denganStatus(string $status): static
    {
        return $this->afterCreating(fn (TautanPendek $t) => $t->forceFill(['status' => $status])->saveQuietly());
    }
}
