<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['doc_type', 'year', 'prefix', 'last_number', 'padding'])]
class InventoryNumberSequence extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_number' => 'integer',
            'padding' => 'integer',
        ];
    }
}
