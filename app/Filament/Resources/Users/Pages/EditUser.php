<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\InteractsWithModuleAccess;
use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\QueryException;

class EditUser extends EditRecord
{
    use InteractsWithModuleAccess;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                // Can't delete the account you're currently signed in as.
                ->visible(fn (): bool => auth()->id() !== $this->record->id)
                // The action below always sends its own notification
                // (success or the FK-blocked explanation) — without
                // this, Filament's default "Deleted" toast fires too,
                // stacking on top of that failure notification even
                // when nothing was actually deleted.
                ->successNotification(null)
                ->action(function (): void {
                    // Re-checked server-side, not just at the visible()
                    // check above.
                    if (auth()->id() === $this->record->id) {
                        Notification::make()->title("You can't delete your own account.")->danger()->send();

                        return;
                    }

                    // Every approval/request/signature in the app is a
                    // permanent FK to a user row, so deleting someone
                    // with any history fails the DB's own constraint
                    // (SQLSTATE 23000) — caught here and turned into a
                    // plain-English redirect to the right tool
                    // (deactivating) instead of a raw error page.
                    try {
                        $this->record->delete();
                    } catch (QueryException $exception) {
                        if ($exception->getCode() !== '23000') {
                            throw $exception;
                        }

                        Notification::make()
                            ->title("{$this->record->name} can't be deleted")
                            ->body('They have approvals, requests, or other records permanently linked to their account — deleting them would break that history. Turn off "Active" on their profile instead: it blocks their login without losing any of it.')
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    Notification::make()->title('User deleted')->success()->send();

                    $this->redirect(UserResource::getUrl('index'));
                }),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return $this->fillModuleLevelFields($data, $this->getRecord());
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // canAccessPanel() gates login on is_active alone — deactivating
        // the account you're currently signed in as would lock you out
        // on the very next request (including the redirect right after
        // this save), same shape as the Delete action's and Inventory
        // Admin's own self-protection above/in InteractsWithModuleAccess.
        if ($this->record->id === auth()->id() && ! ($data['is_active'] ?? true)) {
            Notification::make()
                ->title('Kept your account active')
                ->body("You can't deactivate the account you're currently signed in as — ask another admin to do it.")
                ->warning()
                ->send();

            $data['is_active'] = true;
        }

        return $this->extractModuleLevelFields($data);
    }

    protected function afterSave(): void
    {
        $this->persistModuleLevels($this->record);
    }
}
