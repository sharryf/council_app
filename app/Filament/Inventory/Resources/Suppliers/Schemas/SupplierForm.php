<?php

namespace App\Filament\Inventory\Resources\Suppliers\Schemas;

use App\Models\InventorySupplier;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupplierForm
{
    /**
     * Suggests the next SUP-#### code by looking at the highest existing
     * one, purely as a starting point — not the atomic
     * InventorySequenceService counter every other document number uses,
     * because this is only ever advisory: the field stays editable (a
     * supplier code is often more useful as a memorable mnemonic like
     * "ACME" than a sequential number), and the real safety net is the
     * ->unique() rule below, which catches a collision at save time
     * regardless of how the suggestion was computed.
     */
    public static function suggestNextCode(): string
    {
        $lastNumber = InventorySupplier::query()
            ->where('code', 'like', 'SUP-%')
            ->pluck('code')
            ->map(fn (string $code): int => (int) substr($code, 4))
            ->max() ?? 0;

        return 'SUP-'.str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('Code')
                    ->required()
                    ->maxLength(20)
                    ->unique(ignoreRecord: true)
                    ->default(fn (): string => self::suggestNextCode())
                    ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : $state),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(150),
                TextInput::make('contact_person')
                    ->label('Contact person')
                    ->maxLength(100),
                TextInput::make('phone')
                    ->label('Phone')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->maxLength(150),
                TextInput::make('address')
                    ->label('Address')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }
}
