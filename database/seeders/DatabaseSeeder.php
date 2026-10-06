<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PeranDanIzinSeeder::class,
            UnitSeeder::class,
            SlugTerlarangSeeder::class,
            AturanDomainSeeder::class,
            PengaturanSeeder::class,
            PenggunaAwalSeeder::class,
        ]);
    }
}
