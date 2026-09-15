<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Whether a document's required signers must sign in the order they
 * were added (Sequential — see DocumentSigner::$order) or may all sign
 * in any order (Parallel). Either way, every required signer must sign
 * before the document's status becomes Signed.
 */
enum SigningMode: string implements HasLabel
{
    case Sequential = 'sequential';
    case Parallel = 'parallel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sequential => 'Sequential — signers act in order',
            self::Parallel => 'Parallel — any order',
        };
    }
}
