<?php

namespace App\Models;

use Filament\Actions\Exports\Models\Export as BaseExport;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Export extends BaseExport
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;
}
