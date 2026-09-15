<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['document_id', 'stamp_slot', 'page_number', 'position_x', 'position_y', 'box_width', 'box_height'])]
class DocumentStamp extends Model
{
    protected function casts(): array
    {
        return [
            'stamp_slot' => 'integer',
            'position_x' => 'float',
            'position_y' => 'float',
            'box_width' => 'float',
            'box_height' => 'float',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
