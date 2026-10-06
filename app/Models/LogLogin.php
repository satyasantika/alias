<?php

namespace App\Models;

use App\Enums\MetodeLogin;
use App\Enums\PeristiwaLogin;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PeristiwaLogin $peristiwa
 * @property MetodeLogin $metode
 */
class LogLogin extends Model
{
    use HasUuids;

    protected $table = 'log_login';

    public $timestamps = false;

    protected $fillable = ['user_id', 'email', 'peristiwa', 'metode', 'ip', 'user_agent', 'created_at'];

    protected function casts(): array
    {
        return [
            'peristiwa' => PeristiwaLogin::class,
            'metode' => MetodeLogin::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
