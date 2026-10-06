<?php

namespace App\Models;

use Filament\Actions\Imports\Models\FailedImportRow as BaseFailedImportRow;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class FailedImportRow extends BaseFailedImportRow
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;
}
