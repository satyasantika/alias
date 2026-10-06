<?php

namespace App\Models;

use Filament\Actions\Imports\Models\Import as BaseImport;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Import extends BaseImport
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;
}
