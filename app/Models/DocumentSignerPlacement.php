<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One spot on the document where a signer's signature is composited —
 * a signer can have several (e.g. initials on every page plus a full
 * signature on the last page), all stamped with the same captured
 * signature (DocumentSigner::signature_value/signatureDiskPath()).
 * Mirrors DocumentStamp's one-to-many relationship to its document,
 * just scoped to a signer instead.
 */
#[Fillable(['document_signer_id', 'page_number', 'position_x', 'position_y', 'box_width', 'box_height'])]
class DocumentSignerPlacement extends Model
{
    protected function casts(): array
    {
        return [
            'position_x' => 'float',
            'position_y' => 'float',
            'box_width' => 'float',
            'box_height' => 'float',
        ];
    }

    public function signer(): BelongsTo
    {
        return $this->belongsTo(DocumentSigner::class, 'document_signer_id');
    }
}
