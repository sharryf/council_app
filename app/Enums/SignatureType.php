<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * How a DocumentSigner's signature_value should be interpreted: a
 * storage path to a drawn PNG, or literal typed text rendered in an
 * italic font on the stamped certificate.
 */
enum SignatureType: string implements HasLabel
{
    case Drawn = 'drawn';
    case Typed = 'typed';
    case Saved = 'saved';

    public function getLabel(): string
    {
        return match ($this) {
            self::Drawn => 'Drawn',
            self::Typed => 'Typed',
            self::Saved => 'My saved signature',
        };
    }

    /**
     * Drawn and Saved both resolve to an image file on disk (see
     * DocumentSigner::signatureDiskPath()); only Typed is literal text
     * rendered on the stamped certificate.
     */
    public function isImageBased(): bool
    {
        return $this !== self::Typed;
    }
}
