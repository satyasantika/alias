<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature memakai basis data (MariaDB, db_alias_testing). Arch tidak: uji arsitektur/berkas statis dapat dijalankan
// tanpa basis data; berkas Arch yang butuh basis data memanggil uses(RefreshDatabase::class) sendiri.
pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Arch');
