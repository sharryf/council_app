<?php

namespace App\Filament\Resources\DocumentSigning\Documents\Pages;

use App\Enums\DocumentSigningRole;
use App\Enums\DocumentStatus;
use App\Models\Document;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Admin-only (see App\Enums\DocumentSigningRole — same gate as Settings →
 * Roles/Organization). Permanently deletes old, already-closed documents
 * (Signed/Rejected/Voided — never Draft, which the ordinary per-record
 * delete already covers, and never Pending, which is still active) to
 * reclaim disk space. The retention period is adjustable but floored at
 * 365 days (1 year) — enforced both in the field's own validation and
 * again in the query itself, so the floor holds even if a shorter value
 * somehow reached the server.
 */
class DocumentSigningCleanup extends Page
{
    protected static ?string $moduleKey = 'document-signing';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static ?string $navigationLabel = 'Delete';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Delete old documents';

    private const MIN_DAYS = 365;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = ['days' => self::MIN_DAYS];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user
            && $user->canAccessModule(static::$moduleKey)
            && $user->hasDocumentSigningRole(DocumentSigningRole::Admin);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        $this->form->fill(['days' => self::MIN_DAYS]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('days')
                    ->label('Delete documents older than (days)')
                    ->numeric()
                    ->integer()
                    ->minValue(self::MIN_DAYS)
                    ->default(self::MIN_DAYS)
                    ->required()
                    ->live()
                    ->helperText('Applies only to Signed, Rejected, or Voided documents — Draft and Pending are never affected. Minimum 365 days (1 year).'),
            ])
            ->statePath('data');
    }

    /**
     * The preview partial calls eligibleDocumentsPreview()/totalEligibleCount()
     * directly (see the blade file) instead of receiving them as
     * pre-computed viewData() here — Filament caches the built content
     * schema on the component instance across a request, so a value
     * computed at schema-build time doesn't reflect a deletion that
     * just happened via the header action in that same request/response
     * cycle (confirmed live: the header button's own count updated
     * correctly, but this list stayed stale until a full reload).
     * Method calls evaluated directly in Blade stay fresh on every
     * render regardless of that caching — same fix as
     * PendingSignaturesWidget's reactive count.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form'),
                View::make('filament.resources.document-signing.documents.pages.partials.cleanup-preview'),
            ]);
    }

    public function eligibleDocumentsPreview(): Collection
    {
        return $this->eligibleQuery()->orderBy('created_at')->limit(25)->get();
    }

    public function totalEligibleCount(): int
    {
        return $this->eligibleQuery()->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deleteOld')
                ->label(function (): string {
                    $count = $this->eligibleQuery()->count();

                    return "Delete {$count} ".Str::plural('document', $count);
                })
                ->color('danger')
                ->icon(Heroicon::OutlinedTrash)
                ->requiresConfirmation()
                ->modalHeading('Delete old documents permanently')
                ->modalDescription(function (): string {
                    $count = $this->eligibleQuery()->count();

                    return "This permanently deletes {$count} ".Str::plural('document', $count)." — Signed, Rejected, or Voided, uploaded more than {$this->effectiveDays()} days ago — along with all their files (original, signed copy, signatures), including for documents other people uploaded. This cannot be undone.";
                })
                ->disabled(fn (): bool => $this->eligibleQuery()->count() === 0)
                ->action(function (): void {
                    $documents = $this->eligibleQuery()->get();
                    $count = $documents->count();

                    foreach ($documents as $document) {
                        $document->delete();
                    }

                    Notification::make()
                        ->title("{$count} ".Str::plural('document', $count).' deleted')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function effectiveDays(): int
    {
        return max(self::MIN_DAYS, (int) ($this->data['days'] ?? self::MIN_DAYS));
    }

    private function eligibleQuery(): Builder
    {
        return Document::query()
            ->whereIn('status', [
                DocumentStatus::Signed,
                DocumentStatus::Rejected,
                DocumentStatus::Voided,
            ])
            ->where('created_at', '<', now()->subDays($this->effectiveDays()));
    }
}
