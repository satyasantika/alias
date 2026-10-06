<?php

use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

it('mengembalikan 200 saat db dan redis tersedia', function () {
    Redis::shouldReceive('connection')->andReturn(new class
    {
        public function ping(): string
        {
            return 'PONG';
        }
    });
    Queue::shouldReceive('connection')->andReturn(new class
    {
        public function size(string $q): int
        {
            return 4;
        }
    });

    $this->getJson('/api/health')
        ->assertOk()
        ->assertJson(['db' => 'ok', 'redis' => 'ok', 'antrean' => 4])
        ->assertJsonStructure(['app', 'versi', 'db', 'redis', 'antrean']);
});

it('mengembalikan 503 saat redis gagal', function () {
    Redis::shouldReceive('connection')->andThrow(new RuntimeException('redis mati'));

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJson(['db' => 'ok', 'redis' => 'gagal']);
});
