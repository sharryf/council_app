<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * A canvas signature pad. State is the resulting PNG as a data: URL
 * (or null if nothing has been drawn) — see
 * resources/views/filament/forms/components/signature-pad.blade.php.
 * Callers are responsible for decoding and persisting the data URL as
 * an actual file; the field only captures it.
 */
class SignaturePad extends Field
{
    protected string $view = 'filament.forms.components.signature-pad';
}
