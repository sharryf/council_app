<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use App\Filament\Forms\Components\SignaturePad;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Filament's built-in profile page (name/email/password), plus two
 * signature slots captured here and reused everywhere a signature is
 * needed across the app — currently just Document Signing's Sign
 * action (see DocumentResource::signAction()), which uses whichever
 * slot the user has marked as their default
 * (`User::defaultSignaturePath()`) as a shortcut alongside drawing/
 * typing one fresh. Same two-slot-plus-default pattern as the module's
 * organization stamps (see DocumentSigningOrganization).
 *
 * Signature images are always stored as PNG: SignaturePad's canvas
 * always exports PNG, and the upload path is restricted to PNG so the
 * two sources stay interchangeable wherever a signature file is read
 * (e.g. DocumentStampService, which hard-codes 'PNG' for the FPDI
 * image call).
 */
class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getNameFormComponent(),
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getPasswordConfirmationFormComponent(),
                $this->getCurrentPasswordFormComponent(),
                $this->getSignatureSlotComponent(1, 'Signature 1'),
                $this->getSignatureSlotComponent(2, 'Signature 2'),
                $this->getDefaultSignatureComponent(),
            ]);
    }

    /**
     * Name/email are identity fields other parts of the app key off of
     * (e.g. a document's uploader/signer display, login) — only a
     * system-wide admin may change them, here or anywhere else (see
     * Filament\Resources\Users\UserForm, the actual admin-managed place
     * to edit another user's name/email). A non-admin still sees their
     * own values, just can't edit them; disabled fields aren't
     * dehydrated into saved state by default, so this needs no extra
     * handling in mutateFormDataBeforeSave() to leave them untouched.
     */
    protected function getNameFormComponent(): Component
    {
        return parent::getNameFormComponent()
            ->disabled(fn (): bool => ! (auth()->user()?->hasRole('admin') ?? false))
            ->helperText(fn (): ?string => (auth()->user()?->hasRole('admin') ?? false)
                ? null
                : 'Only an admin can change your name.');
    }

    protected function getEmailFormComponent(): Component
    {
        return parent::getEmailFormComponent()
            ->disabled(fn (): bool => ! (auth()->user()?->hasRole('admin') ?? false))
            ->helperText(fn (): ?string => (auth()->user()?->hasRole('admin') ?? false)
                ? null
                : 'Only an admin can change your email.');
    }

    protected function getSignatureSlotComponent(int $slot, string $label): Component
    {
        /** @var User $user */
        $user = $this->getUser();
        $hasSignature = $user->hasSignatureInSlot($slot);

        return Section::make($label)
            ->schema([
                View::make('filament.pages.partials.signature-preview')
                    ->viewData(['dataUri' => static::signatureDataUri($user, $slot)]),

                Toggle::make("remove_signature_{$slot}")
                    ->label('Remove this signature')
                    ->live()
                    ->visible($hasSignature),

                ToggleButtons::make("signature_source_{$slot}")
                    ->label($hasSignature ? 'Replace signature' : 'Add a signature')
                    ->options(['drawn' => 'Draw', 'uploaded' => 'Upload an image'])
                    ->inline()
                    ->live()
                    ->visible(fn (Get $get): bool => ! $get("remove_signature_{$slot}")),

                SignaturePad::make("drawn_signature_{$slot}")
                    ->label('Draw your signature')
                    ->visible(fn (Get $get): bool => ! $get("remove_signature_{$slot}") && $get("signature_source_{$slot}") === 'drawn'),

                FileUpload::make("uploaded_signature_{$slot}")
                    ->label('Signature image (PNG)')
                    ->image()
                    ->acceptedFileTypes(['image/png'])
                    ->disk('local')
                    ->directory('signatures/users')
                    ->visibility('private')
                    ->visible(fn (Get $get): bool => ! $get("remove_signature_{$slot}") && $get("signature_source_{$slot}") === 'uploaded'),
            ]);
    }

    /**
     * Only shown once both slots actually hold a signature — with zero
     * or one saved there's nothing to choose between, and
     * User::defaultSignaturePath() already falls back to whichever slot
     * exists on its own.
     */
    protected function getDefaultSignatureComponent(): Component
    {
        /** @var User $user */
        $user = $this->getUser();

        return Radio::make('default_signature_slot')
            ->label('Default signature')
            ->helperText('Used wherever a signature is needed across the app.')
            ->options([1 => 'Signature 1', 2 => 'Signature 2'])
            ->inline()
            ->visible($user->hasSignatureInSlot(1) && $user->hasSignatureInSlot(2))
            // Hidden fields aren't dehydrated into form state by
            // default — needed here since this field starts hidden
            // (the user has no signatures yet) but is being filled in
            // the very same save that adds the second one.
            ->dehydratedWhenHidden();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var User $user */
        $user = $this->getUser();

        // disabled() (see getNameFormComponent()/getEmailFormComponent())
        // only blocks editing through the rendered field — Filament's
        // own source warns it doesn't stop the value from being
        // dehydrated and saved if a request bypasses the disabled
        // client-side state, so a non-admin's name/email must be
        // discarded here too rather than trusted from $data.
        if (! (auth()->user()?->hasRole('admin') ?? false)) {
            unset($data['name'], $data['email']);
        }

        $data['signature_path'] = $this->applySignatureSlot($user, $data, 1, $user->signature_path);
        $data['signature_path_2'] = $this->applySignatureSlot($user, $data, 2, $user->signature_path_2);

        $data['default_signature_slot'] = ((int) ($data['default_signature_slot'] ?? 1) === 2 && filled($data['signature_path_2']))
            ? 2
            : 1;

        unset(
            $data['remove_signature_1'], $data['signature_source_1'], $data['drawn_signature_1'], $data['uploaded_signature_1'],
            $data['remove_signature_2'], $data['signature_source_2'], $data['drawn_signature_2'], $data['uploaded_signature_2'],
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applySignatureSlot(User $user, array $data, int $slot, ?string $oldPath): ?string
    {
        $newPath = $oldPath;

        if ($data["remove_signature_{$slot}"] ?? false) {
            $newPath = null;
        } elseif (($data["signature_source_{$slot}"] ?? null) === 'drawn' && filled($data["drawn_signature_{$slot}"] ?? null)) {
            $newPath = $this->storeDrawnSignature($user, $slot, $data["drawn_signature_{$slot}"]);
        } elseif (($data["signature_source_{$slot}"] ?? null) === 'uploaded' && filled($data["uploaded_signature_{$slot}"] ?? null)) {
            $newPath = $data["uploaded_signature_{$slot}"];
        }

        if ($oldPath && $oldPath !== $newPath) {
            Storage::disk('local')->delete($oldPath);
        }

        return $newPath;
    }

    private function storeDrawnSignature(User $user, int $slot, string $dataUrl): string
    {
        [, $encoded] = explode(',', $dataUrl, 2);

        $path = "signatures/users/{$user->id}-{$slot}-".now()->timestamp.'.png';
        Storage::disk('local')->put($path, base64_decode($encoded));

        return $path;
    }

    private static function signatureDataUri(User $user, int $slot): ?string
    {
        if (! $user->hasSignatureInSlot($slot)) {
            return null;
        }

        $bytes = Storage::disk('local')->get($user->signaturePathForSlot($slot));

        return $bytes ? 'data:image/png;base64,'.base64_encode($bytes) : null;
    }
}
